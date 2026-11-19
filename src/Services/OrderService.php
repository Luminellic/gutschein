<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\Code;

/**
 * Bestellungen: anlegen, nach bestätigter Zahlung genau einen Gutschein ausstellen, Mail senden.
 */
final class OrderService
{
    public const STATUS_LABELS = [
        'pending' => 'Offen',
        'paid' => 'Bezahlt',
        'cancelled' => 'Abgebrochen',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly VoucherService $vouchers,
        private readonly Mailer $mailer,
    ) {
    }

    /**
     * Prüft das Kaufformular und legt eine offene Bestellung an.
     *
     * @param array<string, mixed> $product
     * @param array<string, string> $input
     * @return array{0: ?array<string, mixed>, 1: array<string, string>} [Bestellung, Fehler]
     */
    public function create(array $product, array $input, string $provider): array
    {
        $errors = [];
        $amount = 0;
        try {
            $amount = Products::resolveAmount($product, $input['amount'] ?? '');
        } catch (VoucherException $e) {
            $errors['amount'] = $e->getMessage();
        }

        $buyerName = trim($input['buyer_name'] ?? '');
        $buyerEmail = mb_strtolower(trim($input['buyer_email'] ?? ''));
        $recipientName = trim($input['recipient_name'] ?? '');
        $recipientEmail = mb_strtolower(trim($input['recipient_email'] ?? ''));
        $message = trim(str_replace("\r\n", "\n", $input['message'] ?? ''));
        $deliverTo = ($input['deliver_to'] ?? 'buyer') === 'recipient' ? 'recipient' : 'buyer';

        if ($buyerName === '' || mb_strlen($buyerName) > 160) {
            $errors['buyer_name'] = 'Bitte Ihren Namen eingeben.';
        }
        if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['buyer_email'] = 'Bitte eine gültige E-Mail-Adresse eingeben.';
        }
        if (mb_strlen($recipientName) > 160) {
            $errors['recipient_name'] = 'Der Name ist zu lang.';
        }
        if ($deliverTo === 'recipient' && !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['recipient_email'] = 'Für den direkten Versand braucht es die E-Mail-Adresse der beschenkten Person.';
        }
        if (mb_strlen($message) > 300) {
            $errors['message'] = 'Die Nachricht darf höchstens 300 Zeichen lang sein.';
        }
        if (empty($input['terms'])) {
            $errors['terms'] = 'Bitte die Bedingungen akzeptieren.';
        }
        if ($errors !== []) {
            return [null, $errors];
        }

        $now = now()->format('Y-m-d H:i:s');
        $id = $this->db->insert('orders', [
            'public_id' => Code::token(),
            'product_id' => (int) $product['id'],
            'status' => 'pending',
            'amount_cents' => $amount,
            'currency' => 'CHF',
            'buyer_name' => $buyerName,
            'buyer_email' => $buyerEmail,
            'recipient_name' => $recipientName ?: null,
            'recipient_email' => $deliverTo === 'recipient' ? $recipientEmail : null,
            'message' => $message ?: null,
            'deliver_to' => $deliverTo,
            'provider' => $provider,
            'provider_ref' => null,
            'paid_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return [$this->db->one('SELECT * FROM orders WHERE id = ?', [$id]), []];
    }

    /** @return array<string, mixed>|null */
    public function findByPublicId(string $publicId): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $publicId)) {
            return null;
        }
        return $this->db->one('SELECT * FROM orders WHERE public_id = ?', [$publicId]);
    }

    /** @return array<string, mixed>|null */
    public function findByReference(string $provider, string $reference): ?array
    {
        return $this->db->one('SELECT * FROM orders WHERE provider = ? AND provider_ref = ?', [$provider, $reference]);
    }

    public function attachReference(int $orderId, string $reference): void
    {
        $this->db->update('orders', ['provider_ref' => $reference, 'updated_at' => now()->format('Y-m-d H:i:s')], ['id' => $orderId]);
    }

    /** @return array<string, mixed>|null */
    public function voucherFor(int $orderId): ?array
    {
        return $this->db->one('SELECT * FROM vouchers WHERE order_id = ?', [$orderId]);
    }

    /**
     * Markiert die Bestellung als bezahlt und stellt den Gutschein aus. Idempotent:
     * Webhook und Rücksprung dürfen beide (auch gleichzeitig) aufrufen, es entsteht genau ein Gutschein.
     *
     * @return array<string, mixed> der Gutschein
     */
    public function markPaid(int $orderId, ?int $paidAmountCents = null, ?string $currency = null): array
    {
        $created = false;
        $voucher = $this->db->transaction(function (Database $db) use ($orderId, $paidAmountCents, $currency, &$created): array {
            $order = $db->one('SELECT * FROM orders WHERE id = ?' . $db->forUpdate(), [$orderId]);
            if ($order === null) {
                throw new VoucherException('Bestellung nicht gefunden.');
            }
            $existing = $db->one('SELECT * FROM vouchers WHERE order_id = ?', [$orderId]);
            if ($existing !== null) {
                return $existing;
            }
            if ($paidAmountCents !== null && $paidAmountCents !== (int) $order['amount_cents']) {
                throw new VoucherException('Bezahlter Betrag stimmt nicht mit der Bestellung überein.');
            }
            if ($currency !== null && strtoupper($currency) !== 'CHF') {
                throw new VoucherException('Unerwartete Währung ' . $currency . '.');
            }
            $product = $db->one('SELECT * FROM products WHERE id = ?', [$order['product_id']]);
            if ($product === null) {
                throw new VoucherException('Produkt zur Bestellung fehlt.');
            }

            $now = now()->format('Y-m-d H:i:s');
            $db->update('orders', ['status' => 'paid', 'paid_at' => $now, 'updated_at' => $now], ['id' => $orderId]);

            $created = true;
            return $this->vouchers->issue([
                'type' => (string) $product['type'],
                'title' => (string) $product['title'],
                'service_description' => $product['type'] === VoucherService::TYPE_SERVICE ? $product['description'] : null,
                'amount_cents' => (int) $order['amount_cents'],
                'product_id' => (int) $product['id'],
                'order_id' => $orderId,
                'recipient_name' => $order['recipient_name'],
                'message' => $order['message'],
                'buyer_name' => $order['buyer_name'],
                'buyer_email' => $order['buyer_email'],
                'channel' => 'online',
                'validity_months' => $product['validity_months'] !== null ? (int) $product['validity_months'] : null,
                'note' => 'Online-Kauf (' . $order['provider'] . ')',
            ]);
        });

        if ($created) {
            $order = $this->db->one('SELECT * FROM orders WHERE id = ?', [$orderId]);
            $this->sendVoucher($voucher, $order);
        }
        return $voucher;
    }

    public function cancel(int $orderId): void
    {
        $this->db->run(
            "UPDATE orders SET status = 'cancelled', updated_at = ? WHERE id = ? AND status = 'pending'",
            [now()->format('Y-m-d H:i:s'), $orderId]
        );
    }

    /**
     * Sendet den Gutschein per Mail an Käufer:in bzw. direkt an die beschenkte Person.
     * Bei direktem Versand erhält die Käuferin zusätzlich eine Bestätigung.
     *
     * @param array<string, mixed> $voucher
     * @param array<string, mixed>|null $order
     */
    public function sendVoucher(array $voucher, ?array $order, ?string $overrideRecipient = null): bool
    {
        $to = $overrideRecipient;
        if ($to === null) {
            $to = ($order && $order['deliver_to'] === 'recipient' && $order['recipient_email'])
                ? (string) $order['recipient_email']
                : (string) ($voucher['buyer_email'] ?? '');
        }
        if ($to === '') {
            return false;
        }
        $ok = $this->mailer->sendVoucher($to, $voucher, $order);
        if ($overrideRecipient === null && $order && $order['deliver_to'] === 'recipient' && $order['recipient_email']) {
            $this->mailer->sendPurchaseConfirmation((string) $order['buyer_email'], $voucher, $order);
        }
        return $ok;
    }
}
