<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Betriebsspezifische Einstellungen (Name, Texte, Farbe …), im Admin unter «Einstellungen» pflegbar.
 * Hier liegen die Werte, die pro Kundenprojekt angepasst werden.
 */
final class Settings
{
    public const DEFAULTS = [
        'business_name' => 'Musterbetrieb',
        'business_address' => "Musterstrasse 1\n4051 Basel",
        'business_email' => 'info@example.ch',
        'business_phone' => '',
        'business_website' => '',
        'shop_intro' => 'Verschenken Sie einen Gutschein. Sie erhalten ihn nach der Zahlung sofort per E-Mail, zum Ausdrucken oder zum Vorzeigen auf dem Handy.',
        'terms' => "Der Gutschein ist bis zum angegebenen Datum gültig und kann in mehreren Teilbeträgen eingelöst werden. Eine Barauszahlung ist nicht möglich. Bei Verlust wird kein Ersatz geleistet.",
        'accent_color' => '#1F4E79',
        'default_validity_months' => '24',
        'mail_from_name' => '',
        'imprint_url' => '',
        'privacy_url' => '',
    ];

    /** @var array<string, string>|null */
    private ?array $cache = null;

    public function __construct(private readonly Database $db)
    {
    }

    public function get(string $key, string $default = ''): string
    {
        $all = $this->all();
        $value = $all[$key] ?? null;
        if ($value === null || $value === '') {
            return self::DEFAULTS[$key] ?? $default;
        }
        return $value;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, (string) $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach ($this->db->all('SELECT name, value FROM settings') as $row) {
                $this->cache[(string) $row['name']] = (string) $row['value'];
            }
        }
        return $this->cache + self::DEFAULTS;
    }

    /** @param array<string, string> $values */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            $exists = $this->db->value('SELECT COUNT(*) FROM settings WHERE name = ?', [$key]);
            if ((int) $exists > 0) {
                $this->db->update('settings', ['value' => $value], ['name' => $key]);
            } else {
                $this->db->insert('settings', ['name' => $key, 'value' => $value]);
            }
        }
        $this->cache = null;
    }

    /** Textfarbe auf der Akzentfarbe: schwarz bei hellen, weiss bei dunklen Farben (WCAG-Luminanz). */
    public function accentInk(): string
    {
        [$r, $g, $b] = array_map(
            static function (string $hex): float {
                $c = hexdec($hex) / 255;
                return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($this->accent(), 1), 2)
        );
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        return $luminance > 0.18 ? '#000000' : '#FFFFFF';
    }

    /** Akzentfarbe als sicherer Hex-Wert (wird ins CSS geschrieben). */
    public function accent(): string
    {
        $color = $this->get('accent_color');
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? strtoupper($color) : self::DEFAULTS['accent_color'];
    }
}
