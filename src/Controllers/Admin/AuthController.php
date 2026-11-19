<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class AuthController
{
    public function form(Request $request): void
    {
        if (Auth::check()) {
            Response::redirect('/admin');
        }
        echo View::render('admin/login', ['title' => 'Anmelden', 'email' => ''], 'layout-admin');
    }

    public function login(Request $request): void
    {
        Csrf::verify($request);
        $email = $request->input('email');
        $password = (string) ($request->post['password'] ?? '');

        if (Auth::isLocked($email, $request->ip)) {
            Session::flash('error', 'Zu viele Fehlversuche. Bitte in ' . Auth::LOCK_MINUTES . ' Minuten nochmals versuchen.');
            Response::redirect('/admin/login');
        }
        if (!Auth::attempt($email, $password, $request->ip)) {
            Session::flash('error', 'E-Mail oder Passwort ist falsch.');
            echo View::render('admin/login', ['title' => 'Anmelden', 'email' => $email], 'layout-admin');
            return;
        }
        $intended = Session::get('intended');
        Session::forget('intended');
        $target = is_string($intended) && str_starts_with($intended, url('/admin')) ? $intended : url('/admin');
        header('Location: ' . $target, true, 303);
        exit;
    }

    public function logout(Request $request): void
    {
        Csrf::verify($request);
        Auth::logout();
        Response::redirect('/admin/login');
    }
}
