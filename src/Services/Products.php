<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\Money;

/**
 * Gutschein-Produkte:
 * - value + amount_cents      → Wertgutschein mit fixem Betrag
 * - value + amount_cents NULL → Wertgutschein, Betrag frei zwischen min_cents und max_cents
 * - service                   → Leistungsgutschein mit Preis amount_cents
 */
final class Products
{
    public const DEFAULT_MIN = 1000;     // CHF 10
    public const DEFAULT_MAX = 100000;   // CHF 1000

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function active(): array
    {
        return $this->db->all('SELECT * FROM products WHERE active = 1 ORDER BY sort, id');
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all('SELECT * FROM products ORDER BY active DESC, sort, id');
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM products WHERE id = ?', [$id]);
    }

    public static function isCustomAmount(array $product): bool
    {
        return $product['type'] === VoucherService::TYPE_VALUE && $product['amount_cents'] === null;
    }

    public static function priceLabel(array $product): string
    {
        if (self::isCustomAmount($product)) {
            return Money::format((int) ($product['min_cents'] ?? self::DEFAULT_MIN)) . ' bis ' . Money::format((int) ($product['max_cents'] ?? self::DEFAULT_MAX));
        }
        return Money::format((int) $product['amount_cents']);
    }

    /** Ermittelt den zu bezahlenden Betrag und prüft Grenzen. */
    public static function resolveAmount(array $product, string $input): int
    {
        if (!self::isCustomAmount($product)) {
            return (int) $product['amount_cents'];
        }
        $min = (int) ($product['min_cents'] ?? self::DEFAULT_MIN);
        $max = (int) ($product['max_cents'] ?? self::DEFAULT_MAX);
        $amount = Money::parse($input);
        if ($amount === null) {
            throw new VoucherException('Bitte einen gültigen Betrag eingeben, z.B. 80 oder 80.50.');
        }
        if ($amount < $min || $amount > $max) {
            throw new VoucherException('Der Betrag muss zwischen ' . Money::format($min) . ' und ' . Money::format($max) . ' liegen.');
        }
        return $amount;
    }

    /**
     * Validiert und speichert ein Produkt aus dem Admin-Formular.
     *
     * @param array<string, string> $input
     * @return array{0: ?int, 1: array<string, string>} [id, Fehler]
     */
    public function save(?int $id, array $input): array
    {
        $errors = [];
        $type = $input['type'] ?? 'value';
        $mode = $input['amount_mode'] ?? 'fixed';
        $title = trim($input['title'] ?? '');
        if (!in_array($type, [VoucherService::TYPE_VALUE, VoucherService::TYPE_SERVICE], true)) {
            $errors['type'] = 'Bitte einen Typ wählen.';
        }
        if ($title === '' || mb_strlen($title) > 160) {
            $errors['title'] = 'Bitte einen Titel mit höchstens 160 Zeichen eingeben.';
        }

        $amount = $min = $max = null;
        if ($type === VoucherService::TYPE_SERVICE || $mode === 'fixed') {
            $amount = Money::parse($input['amount'] ?? '');
            if ($amount === null || $amount <= 0) {
                $errors['amount'] = 'Bitte einen Betrag grösser als 0 eingeben.';
            }
        } else {
            $min = Money::parse($input['min'] ?? '');
            $max = Money::parse($input['max'] ?? '');
            if ($min === null || $min <= 0) {
                $errors['min'] = 'Bitte einen Mindestbetrag eingeben.';
            }
            if ($max === null || ($min !== null && $max < $min)) {
                $errors['max'] = 'Der Höchstbetrag muss mindestens so hoch sein wie der Mindestbetrag.';
            }
        }

        $validity = trim($input['validity_months'] ?? '');
        if ($validity !== '' && (!ctype_digit($validity) || (int) $validity < 1 || (int) $validity > 120)) {
            $errors['validity_months'] = 'Gültigkeit in Monaten zwischen 1 und 120, oder leer für den Standard.';
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        $now = now()->format('Y-m-d H:i:s');
        $data = [
            'type' => $type,
            'title' => $title,
            'description' => trim($input['description'] ?? '') ?: null,
            'amount_cents' => $amount,
            'min_cents' => $min,
            'max_cents' => $max,
            'validity_months' => $validity === '' ? null : (int) $validity,
            'active' => isset($input['active']) ? 1 : 0,
            'sort' => (int) ($input['sort'] ?? 0),
            'updated_at' => $now,
        ];
        if ($id === null) {
            $id = $this->db->insert('products', $data + ['created_at' => $now]);
        } else {
            $this->db->update('products', $data, ['id' => $id]);
        }
        return [$id, []];
    }
}
