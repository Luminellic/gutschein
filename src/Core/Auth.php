<?php

declare(strict_types=1);

namespace App\Core;

use App\App;

final class Auth
{
    public const MAX_ATTEMPTS = 5;
    public const LOCK_MINUTES = 15;

    /** @var array<string, mixed>|null|false false = noch nicht geladen */
    private static array|null|false $user = false;

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$user === false) {
            $id = Session::get('user_id');
            self::$user = $id
                ? App::db()->one('SELECT id, name, email, role, active FROM users WHERE id = ? AND active = 1', [$id])
                : null;
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Erlaubt Rollen 'staff' (Kasse) und 'admin'. Ohne Login: Weiterleitung zur Anmeldung. */
    public static function requireStaff(): void
    {
        if (!self::check()) {
            Session::set('intended', $_SERVER['REQUEST_URI'] ?? null);
            Response::redirect('/admin/login');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireStaff();
        if (!self::isAdmin()) {
            Response::error(403, 'Für diese Seite braucht es Admin-Rechte.');
        }
    }

    public static function isLocked(string $email, string $ip): bool
    {
        $since = now()->modify('-' . self::LOCK_MINUTES . ' minutes')->format('Y-m-d H:i:s');
        $db = App::db();
        $byEmail = (int) $db->value(
            'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at >= ?',
            [mb_strtolower(trim($email)), $since]
        );
        $byIp = (int) $db->value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at >= ?',
            [$ip, $since]
        );
        // Pro Konto 5 Versuche, pro IP-Adresse 20 Versuche im Zeitfenster.
        return $byEmail >= self::MAX_ATTEMPTS || $byIp >= self::MAX_ATTEMPTS * 4;
    }

    public static function attempt(string $email, string $password, string $ip): bool
    {
        $email = mb_strtolower(trim($email));
        $db = App::db();
        $user = $db->one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $db->insert('login_attempts', ['email' => $email, 'ip' => $ip, 'created_at' => now()->format('Y-m-d H:i:s')]);
            return false;
        }
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $user['id']]);
        }
        $db->run('DELETE FROM login_attempts WHERE email = ?', [$email]);
        $db->update('users', ['last_login_at' => now()->format('Y-m-d H:i:s')], ['id' => $user['id']]);
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        self::$user = false;
        return true;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
    }

    /** Nur für Tests. */
    public static function reset(): void
    {
        self::$user = false;
    }
}
