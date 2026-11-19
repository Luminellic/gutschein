<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\Code;
use App\Support\Money;
use DateTimeImmutable;

/**
 * Ausstellen, Einlösen und Korrigieren von Gutscheinen.
 *
 * Regeln:
 * - Jede Saldoänderung ist eine Zeile in voucher_transactions (Journal).
 * - vouchers.balance_cents wird in derselben Transaktion nachgeführt.
 * - Einlösungen sperren die Gutscheinzeile (SELECT … FOR UPDATE), damit zwei Kassen
 *   nicht gleichzeitig denselben Restwert abbuchen.
 */
final class VoucherService
{
    public const TYPE_VALUE = 'value';
    public const TYPE_SERVICE = 'service';

    public const STATUS_LABELS = [
        'active' => 'Gültig',
        'redeemed' => 'Eingelöst',
        'expired' => 'Abgelaufen',
        'void' => 'Storniert',
    ];

    public const TX_LABELS = [
        'issue' => 'Ausgestellt',
        'redeem' => 'Eingelöst',
        'adjust' => 'Korrektur',
        'void' => 'Storniert',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly Settings $settings,
    ) {
    }

    /** Effektiver Status inkl. «abgelaufen» (wird nicht gespeichert, sondern aus dem Datum berechnet). */
    public static function status(array $voucher, ?DateTimeImmutable $today = null): string
    {
        $status = (string) $voucher['status'];
        if ($status === 'active') {
            $today ??= new DateTimeImmutable('today');
            if ((string) $voucher['valid_until'] < $today->format('Y-m-d')) {
                return 'expired';
            }
        }
        return $status;
    }

    public function validUntil(?int $months): DateTimeImmutable
    {
        $months ??= $this->settings->int('default_validity_months', 24);
        $months = max(1, $months);
        return (new DateTimeImmutable('today'))->modify("+$months months");
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM vouchers WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $input): ?array
    {
        $code = Code::normalize($input);
        if (!Code::isValid($code)) {
            return null;
        }
        return $this->db->one('SELECT * FROM vouchers WHERE code = ?', [$code]);
    }

    /** @return array<string, mixed>|null */
    public function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        return $this->db->one('SELECT * FROM vouchers WHERE token = ?', [$token]);
    }

    /** @return list<array<string, mixed>> */
    public function transactions(int $voucherId): array
    {
        return $this->db->all(
            'SELECT t.*, u.name AS user_name FROM voucher_transactions t
             LEFT JOIN users u ON u.id = t.user_id
             WHERE t.voucher_id = ? ORDER BY t.id DESC',
            [$voucherId]
        );
    }

    /**
     * Stellt einen Gutschein aus und bucht den Anfangswert.
     *
     * @param array{
     *   type: string, title: string, amount_cents: int, service_description?: ?string,
     *   product_id?: ?int, order_id?: ?int, recipient_name?: ?string, message?: ?string,
     *   buyer_name?: ?string, buyer_email?: ?string, channel?: string, valid_until?: ?DateTimeImmutable,
     *   validity_months?: ?int, user_id?: ?int, note?: ?string
     * } $data
     * @return array<string, mixed>
     */
    public function issue(array $data): array
    {
        $amount = (int) $data['amount_cents'];
        if ($amount <= 0) {
            throw new VoucherException('Der Betrag muss grösser als 0 sein.');
        }
        if (!in_array($data['type'], [self::TYPE_VALUE, self::TYPE_SERVICE], true)) {
            throw new VoucherException('Unbekannter Gutscheintyp.');
        }
        $validUntil = $data['valid_until'] ?? $this->validUntil($data['validity_months'] ?? null);
        $now = now()->format('Y-m-d H:i:s');

        $insert = function (Database $db) use ($data, $amount, $validUntil, $now): int {
            $id = $db->insert('vouchers', [
                'code' => $this->uniqueCode(),
                'token' => Code::token(),
                'order_id' => $data['order_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'type' => $data['type'],
                'title' => $data['title'],
                'service_description' => $data['service_description'] ?? null,
                'initial_cents' => $amount,
                'balance_cents' => $amount,
                'currency' => 'CHF',
                'status' => 'active',
                'recipient_name' => $this->nullable($data['recipient_name'] ?? null),
                'message' => $this->nullable($data['message'] ?? null),
                'buyer_name' => $this->nullable($data['buyer_name'] ?? null),
                'buyer_email' => $this->nullable($data['buyer_email'] ?? null),
                'channel' => $data['channel'] ?? 'online',
                'valid_until' => $validUntil->format('Y-m-d'),
                'created_by' => $data['user_id'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $db->insert('voucher_transactions', [
                'voucher_id' => $id,
                'type' => 'issue',
                'amount_cents' => $amount,
                'balance_after_cents' => $amount,
                'note' => $this->nullable($data['note'] ?? null),
                'user_id' => $data['user_id'] ?? null,
                'created_at' => $now,
            ]);
            return $id;
        };

        // Läuft entweder in einer bestehenden Transaktion (Bestellung) oder in einer eigenen.
        $id = $this->db->pdo()->inTransaction() ? $insert($this->db) : $this->db->transaction($insert);
        return $this->find($id) ?? throw new VoucherException('Gutschein konnte nicht gespeichert werden.');
    }

    /**
     * Löst einen Betrag ein. Bei Leistungsgutscheinen wird immer der ganze Restwert eingelöst.
     *
     * @return array<string, mixed> der aktualisierte Gutschein
     */
    public function redeem(int $voucherId, ?int $amountCents, ?int $userId, ?string $note = null): array
    {
        return $this->db->transaction(function (Database $db) use ($voucherId, $amountCents, $userId, $note): array {
            $voucher = $this->lock($db, $voucherId);
            $this->assertRedeemable($voucher);

            $balance = (int) $voucher['balance_cents'];
            $amount = $voucher['type'] === self::TYPE_SERVICE ? $balance : (int) $amountCents;

            if ($amount <= 0) {
                throw new VoucherException('Bitte einen Betrag grösser als 0 eingeben.');
            }
            if ($amount > $balance) {
                throw new VoucherException(
                    'Der Betrag ist höher als der Restwert von ' . Money::format($balance) . '.'
                );
            }

            $newBalance = $balance - $amount;
            $this->writeBalance($db, $voucher, $newBalance, $newBalance === 0 ? 'redeemed' : 'active');
            $this->journal($db, $voucherId, 'redeem', -$amount, $newBalance, $note, $userId);

            return $this->find($voucherId);
        });
    }

    /**
     * Korrekturbuchung (+/−) mit Pflichtnotiz, z.B. bei Fehleinlösung.
     *
     * @return array<string, mixed>
     */
    public function adjust(int $voucherId, int $deltaCents, int $userId, string $note): array
    {
        if (trim($note) === '') {
            throw new VoucherException('Für eine Korrektur braucht es eine Begründung.');
        }
        if ($deltaCents === 0) {
            throw new VoucherException('Die Korrektur darf nicht 0 sein.');
        }
        return $this->db->transaction(function (Database $db) use ($voucherId, $deltaCents, $userId, $note): array {
            $voucher = $this->lock($db, $voucherId);
            if ($voucher['status'] === 'void') {
                throw new VoucherException('Stornierte Gutscheine können nicht korrigiert werden.');
            }
            $newBalance = (int) $voucher['balance_cents'] + $deltaCents;
            if ($newBalance < 0) {
                throw new VoucherException('Der Restwert darf nicht negativ werden.');
            }
            $this->writeBalance($db, $voucher, $newBalance, $newBalance === 0 ? 'redeemed' : 'active');
            $this->journal($db, $voucherId, 'adjust', $deltaCents, $newBalance, $note, $userId);
            return $this->find($voucherId);
        });
    }

    /** @return array<string, mixed> */
    public function void(int $voucherId, int $userId, string $note): array
    {
        if (trim($note) === '') {
            throw new VoucherException('Bitte einen Grund für die Stornierung angeben.');
        }
        return $this->db->transaction(function (Database $db) use ($voucherId, $userId, $note): array {
            $voucher = $this->lock($db, $voucherId);
            if ($voucher['status'] === 'void') {
                throw new VoucherException('Der Gutschein ist bereits storniert.');
            }
            $balance = (int) $voucher['balance_cents'];
            $this->writeBalance($db, $voucher, 0, 'void');
            $this->journal($db, $voucherId, 'void', -$balance, 0, $note, $userId);
            return $this->find($voucherId);
        });
    }

    /** @return array<string, mixed> */
    public function changeValidity(int $voucherId, DateTimeImmutable $validUntil, int $userId): array
    {
        return $this->db->transaction(function (Database $db) use ($voucherId, $validUntil, $userId): array {
            $voucher = $this->lock($db, $voucherId);
            if ($voucher['status'] === 'void') {
                throw new VoucherException('Stornierte Gutscheine können nicht verlängert werden.');
            }
            $db->update('vouchers', [
                'valid_until' => $validUntil->format('Y-m-d'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ], ['id' => $voucherId]);
            $note = 'Gültigkeit von ' . date_ch((string) $voucher['valid_until']) . ' auf ' . $validUntil->format('d.m.Y') . ' geändert';
            $this->journal($db, $voucherId, 'adjust', 0, (int) $voucher['balance_cents'], $note, $userId);
            return $this->find($voucherId);
        });
    }

    /** @param array<string, mixed> $voucher */
    public function assertRedeemable(array $voucher): void
    {
        switch (self::status($voucher)) {
            case 'redeemed':
                throw new VoucherException('Dieser Gutschein ist bereits vollständig eingelöst.');
            case 'void':
                throw new VoucherException('Dieser Gutschein wurde storniert und kann nicht eingelöst werden.');
            case 'expired':
                throw new VoucherException('Dieser Gutschein ist am ' . date_ch((string) $voucher['valid_until']) . ' abgelaufen. Ein Admin kann die Gültigkeit verlängern.');
        }
    }

    /** Summe aller Buchungen, zur Kontrolle gegen den gespeicherten Saldo. */
    public function journalBalance(int $voucherId): int
    {
        return (int) $this->db->value('SELECT COALESCE(SUM(amount_cents), 0) FROM voucher_transactions WHERE voucher_id = ?', [$voucherId]);
    }

    /** @return array<string, mixed> */
    private function lock(Database $db, int $voucherId): array
    {
        $voucher = $db->one('SELECT * FROM vouchers WHERE id = ?' . $db->forUpdate(), [$voucherId]);
        if ($voucher === null) {
            throw new VoucherException('Gutschein nicht gefunden.');
        }
        return $voucher;
    }

    /** @param array<string, mixed> $voucher */
    private function writeBalance(Database $db, array $voucher, int $balance, string $status): void
    {
        $db->update('vouchers', [
            'balance_cents' => $balance,
            'status' => $status,
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ], ['id' => $voucher['id']]);
    }

    private function journal(Database $db, int $voucherId, string $type, int $amount, int $balanceAfter, ?string $note, ?int $userId): void
    {
        $db->insert('voucher_transactions', [
            'voucher_id' => $voucherId,
            'type' => $type,
            'amount_cents' => $amount,
            'balance_after_cents' => $balanceAfter,
            'note' => $this->nullable($note),
            'user_id' => $userId,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function uniqueCode(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = Code::generate();
            if ((int) $this->db->value('SELECT COUNT(*) FROM vouchers WHERE code = ?', [$code]) === 0) {
                return $code;
            }
        }
        throw new VoucherException('Es konnte kein eindeutiger Code erzeugt werden.');
    }

    private function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }
}
