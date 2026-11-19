<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Beträge werden durchgehend in Rappen (int) geführt.
 * Formatierung nach Schweizer Schreibweise: CHF 1’250.00
 */
final class Money
{
    public static function format(int $cents, bool $withCurrency = true): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $francs = intdiv($cents, 100);
        $rappen = $cents % 100;
        $formatted = number_format($francs, 0, '.', '’') . '.' . str_pad((string) $rappen, 2, '0', STR_PAD_LEFT);
        $formatted = ($negative ? '−' : '') . $formatted;
        return $withCurrency ? 'CHF ' . $formatted : $formatted;
    }

    /** Eingabe für Formularfelder: 1250.5 → "1250.50" */
    public static function toInput(?int $cents): string
    {
        if ($cents === null) {
            return '';
        }
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Parst Benutzereingaben wie "50", "50.5", "50,50", "1'250.00", "CHF 20".
     * Gibt null zurück, wenn die Eingabe kein gültiger Betrag ist.
     */
    public static function parse(string $input): ?int
    {
        $clean = str_replace(['CHF', 'Fr.', ' ', "'", '’', "\u{00A0}"], '', trim($input));
        $clean = str_replace(',', '.', $clean);
        if ($clean === '' || !preg_match('/^-?\d+(\.\d{1,2})?$/', $clean)) {
            return null;
        }
        $negative = str_starts_with($clean, '-');
        $clean = ltrim($clean, '-');
        [$francs, $rappen] = array_pad(explode('.', $clean, 2), 2, '0');
        $cents = (int) $francs * 100 + (int) str_pad($rappen, 2, '0');
        return $negative ? -$cents : $cents;
    }
}
