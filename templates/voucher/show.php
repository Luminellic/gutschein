<?php
/** @var array<string, mixed> $voucher @var bool $staff */
use App\App;
use App\Core\View;
use App\Services\VoucherService;
use App\Support\Code;

$status = VoucherService::status($voucher);
?>
<div class="wrap voucher-page">
    <?php if ($staff): ?>
        <div class="notice info no-print">
            <p>Sie sind als Personal angemeldet.
               <a href="<?= e(url('/admin/einloesen/' . $voucher['id'])) ?>"><strong>Diesen Gutschein einlösen</strong></a></p>
        </div>
    <?php endif ?>

    <?= View::partial('partials/ticket', [
        'issuer' => App::settings()->get('business_name'),
        'title' => $voucher['title'],
        'valueCents' => (int) $voucher['initial_cents'],
        'type' => $voucher['type'],
        'serviceDescription' => $voucher['service_description'],
        'recipientName' => $voucher['recipient_name'],
        'message' => $voucher['message'],
        'code' => Code::format((string) $voucher['code']),
        'validUntil' => date_ch((string) $voucher['valid_until']),
        'balanceCents' => (int) $voucher['balance_cents'],
        'qrSrc' => url('/g/' . $voucher['token'] . '/qr.svg'),
        'status' => $status,
    ]) ?>

    <div class="voucher-tools">
        <button class="button" type="button" data-print>Drucken oder als PDF speichern</button>
    </div>
    <p class="voucher-help">
        Zum Einlösen den Gutschein ausgedruckt oder auf dem Handy vorzeigen. Wertgutscheine können in mehreren Teilbeträgen eingelöst werden; der Restwert steht immer aktuell auf dieser Seite.
        Diesen Link bitte nur an die beschenkte Person weitergeben.
    </p>
</div>
