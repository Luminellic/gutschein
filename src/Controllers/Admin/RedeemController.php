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
use App\Services\VoucherException;
use App\Support\Code;
use App\Support\Money;

/** Einlösen an der Kasse. Für Rollen staff und admin. */
final class RedeemController
{
    public function search(Request $request): void
    {
        Auth::requireStaff();
        $input = $request->input('code');
        if ($input !== '') {
            $vouchers = App::vouchers();
            // Akzeptiert auch eine gescannte URL /g/{token}
            $voucher = preg_match('#/g/([a-f0-9]{32})#', $input, $m)
                ? $vouchers->findByToken($m[1])
                : $vouchers->findByCode($input);
            if ($voucher !== null) {
                Response::redirect('/admin/einloesen/' . $voucher['id']);
            }
            Session::flash('error', 'Kein Gutschein mit dem Code ' . Code::format(Code::normalize($input)) . ' gefunden. Bitte Eingabe prüfen.');
        }
        echo View::render('admin/redeem-search', [
            'title' => 'Einlösen',
            'code' => $input,
        ], 'layout-admin');
    }

    public function show(Request $request, string $id): void
    {
        Auth::requireStaff();
        $vouchers = App::vouchers();
        $voucher = $vouchers->find((int) $id) ?? Response::error(404, 'Gutschein nicht gefunden.');
        echo View::render('admin/redeem', [
            'title' => 'Einlösen',
            'voucher' => $voucher,
            'transactions' => array_slice($vouchers->transactions((int) $voucher['id']), 0, 5),
            'done' => Session::get('_redeemed'),
        ], 'layout-admin');
        Session::forget('_redeemed');
    }

    public function redeem(Request $request, string $id): void
    {
        Auth::requireStaff();
        Csrf::verify($request);
        $vouchers = App::vouchers();
        $voucher = $vouchers->find((int) $id) ?? Response::error(404, 'Gutschein nicht gefunden.');

        $amount = null;
        if ($voucher['type'] === 'value') {
            $amount = $request->input('mode') === 'all' ? (int) $voucher['balance_cents'] : Money::parse($request->input('amount'));
            if ($amount === null) {
                Session::flash('error', 'Bitte einen gültigen Betrag eingeben, z.B. 25 oder 25.50.');
                Response::redirect('/admin/einloesen/' . $voucher['id']);
            }
        }

        try {
            $before = (int) $voucher['balance_cents'];
            $updated = $vouchers->redeem((int) $voucher['id'], $amount, Auth::id(), $request->input('note'));
            Session::set('_redeemed', $before - (int) $updated['balance_cents']);
        } catch (VoucherException $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect('/admin/einloesen/' . $voucher['id']);
    }
}
