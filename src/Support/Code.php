<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Gutscheincodes: 12 Zeichen aus 32 gut lesbaren Zeichen (ohne 0, O, 1, I), ergibt 60 Bit.
 * Gespeichert ohne Bindestriche, angezeigt als XXXX-XXXX-XXXX.
 */
final class Code
{
    public const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    public const LENGTH = 12;

    public static function generate(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }
        return $code;
    }

    /** Für die Suche: Grossbuchstaben, ohne Leer- und Trennzeichen. */
    public static function normalize(string $input): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');
    }

    public static function isValid(string $code): bool
    {
        return strlen($code) === self::LENGTH && strspn($code, self::ALPHABET) === self::LENGTH;
    }

    public static function format(string $code): string
    {
        return implode('-', str_split($code, 4));
    }

    public static function token(): string
    {
        return bin2hex(random_bytes(16));
    }
}
