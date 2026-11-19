<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class UserController
{
    public const MIN_PASSWORD = 10;

    public function index(Request $request): void
    {
        Auth::requireAdmin();
        echo View::render('admin/users', [
            'title' => 'Benutzer',
            'users' => App::db()->all('SELECT id, name, email, role, active, last_login_at FROM users ORDER BY active DESC, name'),
            'old' => Session::takeOld(),
        ], 'layout-admin');
    }

    public function store(Request $request): void
    {
        Auth::requireAdmin();
        Csrf::verify($request);
        $name = $request->input('name');
        $email = mb_strtolower($request->input('email'));
        $password = (string) ($request->post['password'] ?? '');
        $role = $request->input('role') === 'admin' ? 'admin' : 'staff';

        $error = match (true) {
            $name === '' => 'Bitte einen Namen eingeben.',
            !filter_var($email, FILTER_VALIDATE_EMAIL) => 'Bitte eine gültige E-Mail-Adresse eingeben.',
            mb_strlen($password) < self::MIN_PASSWORD => 'Das Passwort braucht mindestens ' . self::MIN_PASSWORD . ' Zeichen.',
            (int) App::db()->value('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0 => 'Diese E-Mail-Adresse ist bereits erfasst.',
            default => null,
        };
        if ($error !== null) {
            Session::keepOld(['name' => $name, 'email' => $email, 'role' => $role]);
            Session::flash('error', $error);
            Response::redirect('/admin/benutzer');
        }
        App::db()->insert('users', [
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'active' => 1,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
        Session::flash('success', $name . ' wurde erfasst.');
        Response::redirect('/admin/benutzer');
    }

    public function update(Request $request, string $id): void
    {
        Auth::requireAdmin();
        Csrf::verify($request);
        $db = App::db();
        $user = $db->one('SELECT * FROM users WHERE id = ?', [(int) $id]) ?? Response::error(404, 'Benutzer nicht gefunden.');
        $isSelf = (int) $user['id'] === Auth::id();

        switch ($request->input('action')) {
            case 'toggle':
                if ($isSelf) {
                    Session::flash('error', 'Das eigene Konto kann nicht deaktiviert werden.');
                    break;
                }
                $db->update('users', ['active' => (int) $user['active'] ? 0 : 1], ['id' => $user['id']]);
                Session::flash('success', $user['name'] . ((int) $user['active'] ? ' deaktiviert.' : ' aktiviert.'));
                break;
            case 'role':
                if ($isSelf) {
                    Session::flash('error', 'Die eigene Rolle kann nicht geändert werden.');
                    break;
                }
                $role = $user['role'] === 'admin' ? 'staff' : 'admin';
                $db->update('users', ['role' => $role], ['id' => $user['id']]);
                Session::flash('success', $user['name'] . ' ist jetzt ' . ($role === 'admin' ? 'Admin.' : 'Kasse.'));
                break;
            case 'password':
                $password = (string) ($request->post['password'] ?? '');
                if (mb_strlen($password) < self::MIN_PASSWORD) {
                    Session::flash('error', 'Das Passwort braucht mindestens ' . self::MIN_PASSWORD . ' Zeichen.');
                    break;
                }
                $db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $user['id']]);
                Session::flash('success', 'Passwort für ' . $user['name'] . ' geändert.');
                break;
        }
        Response::redirect('/admin/benutzer');
    }
}
