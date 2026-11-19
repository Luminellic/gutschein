<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\QrCode;

final class VoucherController
{
    public function show(Request $request, string $token): void
    {
        $voucher = App::vouchers()->findByToken($token) ?? Response::error(404, 'Diesen Gutschein gibt es nicht. Bitte den Link prüfen.');
        echo View::render('voucher/show', [
            'title' => 'Gutschein ' . $voucher['title'],
            'voucher' => $voucher,
            'staff' => Auth::check(),
        ]);
    }

    public function qr(Request $request, string $token): void
    {
        $voucher = App::vouchers()->findByToken($token) ?? Response::error(404, 'Nicht gefunden.');
        header('Cache-Control: private, max-age=86400');
        Response::text(QrCode::svg(absolute_url('/g/' . $voucher['token'])), 200, 'image/svg+xml');
    }
}
