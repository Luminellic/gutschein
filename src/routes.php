<?php

declare(strict_types=1);

use App\Controllers\Admin;
use App\Controllers\ShopController;
use App\Controllers\VoucherController;
use App\Controllers\WebhookController;
use App\Core\Router;

return static function (Router $r): void {
    // Öffentlich: Shop und Kauf
    $shop = new ShopController();
    $r->get('/', [$shop, 'index']);
    $r->get('/gutschein/{id}', [$shop, 'product']);
    $r->post('/gutschein/{id}', [$shop, 'buy']);
    $r->get('/bestellung/{order}', [$shop, 'order']);
    $r->get('/zahlung/demo/{order}', [$shop, 'demoPayment']);
    $r->post('/zahlung/demo/{order}', [$shop, 'demoPaymentSubmit']);

    // Öffentlich: Gutschein ansehen
    $voucher = new VoucherController();
    $r->get('/g/{token}', [$voucher, 'show']);
    $r->get('/g/{token}/qr.svg', [$voucher, 'qr']);

    // Zahlungsanbieter
    $r->post('/webhook/stripe', [new WebhookController(), 'stripe']);

    // Admin und Kasse
    $auth = new Admin\AuthController();
    $r->get('/admin/login', [$auth, 'form']);
    $r->post('/admin/login', [$auth, 'login']);
    $r->post('/admin/logout', [$auth, 'logout']);

    $r->get('/admin', [new Admin\DashboardController(), 'index']);

    $redeem = new Admin\RedeemController();
    $r->get('/admin/einloesen', [$redeem, 'search']);
    $r->get('/admin/einloesen/{id}', [$redeem, 'show']);
    $r->post('/admin/einloesen/{id}', [$redeem, 'redeem']);

    $vouchers = new Admin\VoucherAdminController();
    $r->get('/admin/gutscheine', [$vouchers, 'index']);
    $r->get('/admin/gutscheine/neu', [$vouchers, 'create']);
    $r->post('/admin/gutscheine/neu', [$vouchers, 'store']);
    $r->get('/admin/gutscheine/{id}', [$vouchers, 'show']);
    $r->post('/admin/gutscheine/{id}/gueltigkeit', [$vouchers, 'validity']);
    $r->post('/admin/gutscheine/{id}/korrektur', [$vouchers, 'adjust']);
    $r->post('/admin/gutscheine/{id}/storno', [$vouchers, 'void']);
    $r->post('/admin/gutscheine/{id}/mail', [$vouchers, 'resend']);

    $r->get('/admin/bestellungen', [new Admin\OrderAdminController(), 'index']);

    $products = new Admin\ProductController();
    $r->get('/admin/produkte', [$products, 'index']);
    $r->get('/admin/produkte/neu', [$products, 'form']);
    $r->post('/admin/produkte/neu', [$products, 'save']);
    $r->get('/admin/produkte/{id}', [$products, 'form']);
    $r->post('/admin/produkte/{id}', [$products, 'save']);

    $settings = new Admin\SettingsController();
    $r->get('/admin/einstellungen', [$settings, 'form']);
    $r->post('/admin/einstellungen', [$settings, 'save']);

    $users = new Admin\UserController();
    $r->get('/admin/benutzer', [$users, 'index']);
    $r->post('/admin/benutzer', [$users, 'store']);
    $r->post('/admin/benutzer/{id}', [$users, 'update']);

    $export = new Admin\ExportController();
    $r->get('/admin/export/gutscheine', [$export, 'vouchers']);
    $r->get('/admin/export/buchungen', [$export, 'transactions']);

    $mails = new Admin\MailLogController();
    $r->get('/admin/mails', [$mails, 'index']);
    $r->get('/admin/mails/{id}', [$mails, 'show']);
};
