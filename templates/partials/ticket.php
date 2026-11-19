<?php
/**
 * Der Gutschein als Billett. Wird im Shop (Vorschau), auf der Gutscheinseite und im Admin verwendet.
 *
 * @var string      $issuer
 * @var string      $title
 * @var int         $valueCents
 * @var string      $type          value | service
 * @var string|null $serviceDescription
 * @var string|null $recipientName
 * @var string|null $message
 * @var string|null $code          formatiert, null in der Vorschau
 * @var string      $validUntil    d.m.Y
 * @var int|null    $balanceCents  null = nicht anzeigen
 * @var string|null $qrSrc
 * @var string|null $status        effektiver Status (active, redeemed, expired, void)
 * @var bool        $preview
 */
use App\Services\VoucherService;
use App\Support\Money;

$preview ??= false;
$status ??= 'active';
$inactive = $status !== 'active';
$francs = Money::format($valueCents, false);
?>
<article class="ticket<?= $inactive ? ' is-inactive' : '' ?>"<?= $preview ? ' data-preview aria-live="polite"' : '' ?> aria-label="Gutschein">
    <div class="ticket-main">
        <div class="ticket-issuer"><?= e($issuer) ?></div>
        <h2 class="ticket-title"><?= e($title) ?></h2>
        <div class="ticket-value"><span class="cur">CHF</span><span data-preview-amount><?= e($francs) ?></span></div>
        <?php if ($type === 'service' && !empty($serviceDescription)): ?>
            <p class="ticket-service"><?= e($serviceDescription) ?></p>
        <?php endif ?>
        <div class="ticket-for">
            <div class="to" data-preview-recipient<?= empty($recipientName) && !$preview ? ' hidden' : '' ?>>
                <?= !empty($recipientName) ? 'Für ' . e($recipientName) : ($preview ? 'Für …' : '') ?>
            </div>
            <p class="ticket-message" data-preview-message<?= empty($message) ? ' hidden' : '' ?>><?= e($message ?? '') ?></p>
        </div>
    </div>
    <div class="ticket-stub">
        <?php if (!empty($qrSrc)): ?>
            <div class="ticket-qr"><img src="<?= e($qrSrc) ?>" alt="QR-Code zum Einlösen" width="152" height="152"></div>
        <?php else: ?>
            <div class="ticket-qr-placeholder">QR-Code zum Einlösen</div>
        <?php endif ?>
        <dl class="ticket-meta">
            <dt>Code</dt>
            <dd class="ticket-code"><?= e($code ?? 'XXXX-XXXX-XXXX') ?></dd>
            <dt>Gültig bis</dt>
            <dd><?= e($validUntil) ?></dd>
            <?php if ($balanceCents !== null && $type === 'value'): ?>
                <dt>Restwert</dt>
                <dd><?= e(Money::format($balanceCents)) ?></dd>
            <?php endif ?>
            <?php if ($inactive): ?>
                <dt class="visually-hidden">Status</dt>
                <dd><span class="ticket-status"><?= e(VoucherService::STATUS_LABELS[$status] ?? $status) ?></span></dd>
            <?php endif ?>
        </dl>
    </div>
</article>
