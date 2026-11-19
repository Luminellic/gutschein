<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class MailLogController
{
    public function index(Request $request): void
    {
        Auth::requireAdmin();
        echo View::render('admin/mails', [
            'title' => 'Mail-Protokoll',
            'mails' => App::db()->all('SELECT id, recipient, subject, status, error, voucher_id, created_at FROM mail_log ORDER BY id DESC LIMIT 100'),
        ], 'layout-admin');
    }

    /** Zeigt eine gespeicherte Mail in einer abgeschotteten Ansicht (CSP sandbox, keine Skripte). */
    public function show(Request $request, string $id): void
    {
        Auth::requireAdmin();
        $mail = App::db()->one('SELECT * FROM mail_log WHERE id = ?', [(int) $id]) ?? Response::error(404, 'Mail nicht gefunden.');
        header('Content-Security-Policy: sandbox; default-src \'none\'; style-src \'unsafe-inline\'; img-src \'self\' data:', true);
        Response::text((string) $mail['body_html'], 200, 'text/html');
    }
}
