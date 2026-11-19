<?php

declare(strict_types=1);

namespace Tests;

use App\App;
use App\Core\Database;
use App\Services\Mailer;
use App\Services\Settings;
use PDO;

/**
 * Jeder Test bekommt eine frische SQLite-Datenbank im Speicher mit dem Schema aus database/schema.sqlite.sql.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected Database $db;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $this->db = new Database($pdo);
        $schema = (string) file_get_contents(dirname(__DIR__) . '/database/schema.sqlite.sql');
        $schema = preg_replace('/^\s*--.*$/m', '', $schema) ?? '';
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $pdo->exec($statement);
        }
        App::setDb($this->db);
        App::setMailer(new Mailer($this->db, new Settings($this->db)));
    }

    /** @return array<string, mixed> */
    protected function product(array $overrides = []): array
    {
        $now = now()->format('Y-m-d H:i:s');
        $id = $this->db->insert('products', $overrides + [
            'type' => 'value',
            'title' => 'Wertgutschein',
            'description' => null,
            'amount_cents' => null,
            'min_cents' => 2000,
            'max_cents' => 50000,
            'validity_months' => null,
            'active' => 1,
            'sort' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->db->one('SELECT * FROM products WHERE id = ?', [$id]);
    }

    protected function user(string $role = 'staff'): int
    {
        return $this->db->insert('users', [
            'name' => 'Kasse',
            'email' => uniqid('k', true) . '@example.ch',
            'password_hash' => password_hash('geheim-geheim', PASSWORD_DEFAULT),
            'role' => $role,
            'active' => 1,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }
}
