<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Settings;

final class SettingsController
{
    public function form(Request $request): void
    {
        Auth::requireAdmin();
        echo View::render('admin/settings', [
            'title' => 'Einstellungen',
            'values' => App::settings()->all(),
            'errors' => Session::get('_errors', []),
            'payment' => App::payment()->name(),
            'mailDriver' => Env::get('MAIL_DRIVER', 'log'),
        ], 'layout-admin');
        Session::forget('_errors');
    }

    public function save(Request $request): void
    {
        Auth::requireAdmin();
        Csrf::verify($request);
        $values = [];
        foreach (array_keys(Settings::DEFAULTS) as $key) {
            $values[$key] = trim(str_replace("\r\n", "\n", $request->input($key)));
        }
        $errors = [];
        if ($values['business_name'] === '') {
            $errors['business_name'] = 'Bitte den Namen des Betriebs eingeben.';
        }
        if ($values['business_email'] !== '' && !filter_var($values['business_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['business_email'] = 'Diese E-Mail-Adresse ist ungültig.';
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $values['accent_color'])) {
            $errors['accent_color'] = 'Bitte eine Farbe im Format #1F4E79 eingeben.';
        }
        if (!ctype_digit($values['default_validity_months']) || (int) $values['default_validity_months'] < 1 || (int) $values['default_validity_months'] > 120) {
            $errors['default_validity_months'] = 'Bitte eine Zahl zwischen 1 und 120 eingeben.';
        }
        foreach (['business_website', 'imprint_url', 'privacy_url'] as $key) {
            if ($values[$key] !== '' && !preg_match('#^https?://#', $values[$key])) {
                $errors[$key] = 'Bitte eine vollständige Adresse mit https:// eingeben.';
            }
        }
        if ($errors !== []) {
            Session::set('_errors', $errors);
            Session::flash('error', 'Bitte die markierten Felder prüfen. Es wurde nichts gespeichert.');
            Response::redirect('/admin/einstellungen');
        }
        App::settings()->save($values);
        Session::flash('success', 'Einstellungen gespeichert.');
        Response::redirect('/admin/einstellungen');
    }
}
