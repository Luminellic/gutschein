<?php

declare(strict_types=1);

/**
 * Einrichtung: Tabellen anlegen, ersten Admin erstellen, optional Demodaten.
 *
 *   php bin/install.php --admin-email=you@example.ch --admin-name="Felix" [--admin-password=…] [--demo]
 *
 * Ohne --admin-password wird ein Passwort erzeugt und ausgegeben.
 * Mehrfach ausführbar: bestehende Tabellen und Benutzer bleiben erhalten.
 */

use App\App;
use App\Services\Settings;

require dirname(__DIR__) . '/src/bootstrap.php';

$options = getopt('', ['admin-email:', 'admin-name:', 'admin-password:', 'demo', 'help']);
if (isset($options['help'])) {
    echo "php bin/install.php --admin-email=… --admin-name=… [--admin-password=…] [--demo]\n";
    exit(0);
}

$db = App::db();

// 1. Schema
$sqlite = $db->driver() === 'sqlite';
if ($sqlite && (int) $db->value("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'vouchers'") > 0) {
    echo "Tabellen existieren bereits.\n";
} else {
    $schema = (string) file_get_contents(APP_ROOT . ($sqlite ? '/database/schema.sqlite.sql' : '/database/schema.sql'));
    $schema = preg_replace('/^\s*--.*$/m', '', $schema) ?? '';
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
        $db->pdo()->exec($statement);
    }
    echo "Tabellen sind bereit.\n";
}

// 2. Admin
$email = mb_strtolower(trim((string) ($options['admin-email'] ?? '')));
if ($email !== '') {
    if ((int) $db->value('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
        echo "Admin $email existiert bereits, unverändert.\n";
    } else {
        $password = (string) ($options['admin-password'] ?? bin2hex(random_bytes(8)));
        if (mb_strlen($password) < 10) {
            fwrite(STDERR, "Das Passwort braucht mindestens 10 Zeichen.\n");
            exit(1);
        }
        $db->insert('users', [
            'name' => (string) ($options['admin-name'] ?? 'Admin'),
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
            'active' => 1,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
        echo "Admin erstellt: $email\n";
        if (!isset($options['admin-password'])) {
            echo "Passwort: $password  (bitte nach der ersten Anmeldung ändern)\n";
        }
    }
} elseif ((int) $db->value('SELECT COUNT(*) FROM users') === 0) {
    echo "Hinweis: Noch kein Benutzer. Mit --admin-email=… einen Admin erstellen.\n";
}

// 3. Demodaten
if (isset($options['demo'])) {
    if ((int) $db->value('SELECT COUNT(*) FROM products') > 0) {
        echo "Es gibt bereits Produkte, Demodaten werden nicht eingefügt.\n";
    } else {
        $now = now()->format('Y-m-d H:i:s');
        $products = [
            ['value', 'Wertgutschein', 'Frei wählbarer Betrag, einlösbar für alle Angebote und in mehreren Teilen.', null, 2000, 50000, null, 10],
            ['value', 'Gutschein CHF 50', 'Der Klassiker zum Verschenken.', 5000, null, null, null, 20],
            ['service', 'Massage 60 Minuten', 'Klassische Ganzkörpermassage, 60 Minuten.', 12000, null, null, 12, 30],
        ];
        foreach ($products as [$type, $title, $description, $amount, $min, $max, $validity, $sort]) {
            $db->insert('products', [
                'type' => $type, 'title' => $title, 'description' => $description,
                'amount_cents' => $amount, 'min_cents' => $min, 'max_cents' => $max,
                'validity_months' => $validity, 'active' => 1, 'sort' => $sort,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        App::settings()->save([
            'business_name' => 'Atelier Rheinblick',
            'business_address' => "Rheinsprung 1\n4051 Basel",
            'business_email' => 'hallo@example.ch',
        ] + array_intersect_key(Settings::DEFAULTS, ['default_validity_months' => 1]));
        echo "Demodaten eingefügt (3 Produkte, Beispielbetrieb).\n";
    }
}

echo "Fertig.\n";
