<?php
/**
 * Kaufbestätigung, wenn der Gutschein direkt an die beschenkte Person geht.
 *
 * @var array<string, mixed> $voucher
 * @var array<string, mixed> $order
 * @var \App\Services\Settings $settings
 */
use App\Support\Money;

$business = $settings->get('business_name');
?>
<!doctype html>
<html lang="de-CH">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title><?= e($business) ?></title></head>
<body style="margin:0; padding:0; background:#eef0f1; font-family:Helvetica, Arial, sans-serif; color:#000;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f1;">
<tr><td align="center" style="padding:32px 16px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
        <tr><td style="padding:0 0 16px; font-size:15px; font-weight:bold;"><?= e($business) ?></td></tr>
        <tr><td style="background:#ffffff; border-radius:10px; padding:32px; font-size:16px; line-height:1.5;">
            <p style="margin:0 0 16px;">Guten Tag <?= e($order['buyer_name']) ?></p>
            <p style="margin:0 0 16px;">Vielen Dank für Ihren Kauf. Der Gutschein «<?= e($voucher['title']) ?>» über <?= e(Money::format((int) $voucher['initial_cents'])) ?> wurde an <strong><?= e($order['recipient_email']) ?></strong> gesendet.</p>
            <p style="margin:0;">Eine Kopie finden Sie hier: <a href="<?= e(absolute_url('/g/' . $voucher['token'])) ?>" style="color:#000;">Gutschein ansehen</a></p>
        </td></tr>
        <tr><td style="padding:16px 4px; font-size:12px; color:#6e757c; white-space:pre-line;"><?= e($business . "\n" . $settings->get('business_address')) ?></td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
