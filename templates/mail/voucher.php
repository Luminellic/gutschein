<?php
/**
 * HTML-Mail mit dem Gutschein. Inline-Styles und Tabellen, damit sie in allen Mailprogrammen gleich aussieht.
 *
 * @var array<string, mixed> $voucher
 * @var array<string, mixed>|null $order
 * @var bool $forRecipient
 * @var \App\Services\Settings $settings
 */
use App\Support\Code;
use App\Support\Money;

$accent = $settings->accent();
$accentInk = $settings->accentInk();
$business = $settings->get('business_name');
$link = absolute_url('/g/' . $voucher['token']);
?>
<!doctype html>
<html lang="de-CH">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title><?= e($business) ?></title></head>
<body style="margin:0; padding:0; background:#eef0f1; font-family:Helvetica, Arial, sans-serif; color:#000;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f1;">
<tr><td align="center" style="padding:32px 16px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
        <tr><td style="padding:0 0 16px; font-size:15px; font-weight:bold;"><?= e($business) ?></td></tr>
        <tr><td style="background:#ffffff; border-radius:10px; padding:32px;">
            <p style="margin:0 0 20px; font-size:16px; line-height:1.5;">
                <?php if ($forRecipient): ?>
                    <?= e($order['buyer_name'] ?? '') ?> schenkt Ihnen einen Gutschein von <?= e($business) ?>.
                <?php else: ?>
                    Vielen Dank für Ihren Kauf. Hier ist Ihr Gutschein von <?= e($business) ?>.
                <?php endif ?>
            </p>
            <p style="margin:0; font-size:15px; color:#4b5157;"><?= e($voucher['title']) ?></p>
            <p style="margin:4px 0 20px; font-size:36px; font-weight:bold; letter-spacing:-1px;"><?= e(Money::format((int) $voucher['initial_cents'])) ?></p>
            <?php if (!empty($voucher['recipient_name'])): ?>
                <p style="margin:0 0 6px; font-size:16px; font-weight:bold;">Für <?= e($voucher['recipient_name']) ?></p>
            <?php endif ?>
            <?php if (!empty($voucher['message'])): ?>
                <p style="margin:0 0 20px; font-size:16px; line-height:1.5; color:#4b5157; white-space:pre-line;"><?= e($voucher['message']) ?></p>
            <?php endif ?>
            <table role="presentation" cellpadding="0" cellspacing="0" style="border-top:2px dashed #d5d9dc; width:100%; margin:8px 0 24px;">
                <tr>
                    <td style="padding-top:16px; font-size:13px; color:#6e757c;">Code</td>
                    <td style="padding-top:16px; font-size:13px; color:#6e757c;">Gültig bis</td>
                </tr>
                <tr>
                    <td style="font-size:17px; font-weight:bold; letter-spacing:1px;"><?= e(Code::format((string) $voucher['code'])) ?></td>
                    <td style="font-size:17px; font-weight:bold;"><?= e(date_ch((string) $voucher['valid_until'])) ?></td>
                </tr>
            </table>
            <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="background:<?= e($accent) ?>; border-radius:6px;">
                <a href="<?= e($link) ?>" style="display:inline-block; padding:12px 20px; color:<?= e($accentInk) ?>; font-weight:bold; text-decoration:none; font-size:16px;">Gutschein öffnen</a>
            </td></tr></table>
            <p style="margin:20px 0 0; font-size:14px; line-height:1.5; color:#4b5157;">
                Auf der Gutscheinseite finden Sie den QR-Code zum Einlösen und können den Gutschein ausdrucken. Zum Einlösen den Gutschein vorzeigen.
            </p>
        </td></tr>
        <tr><td style="padding:16px 4px; font-size:12px; line-height:1.5; color:#6e757c; white-space:pre-line;"><?= e($business . "\n" . $settings->get('business_address') . ($settings->get('business_email') !== '' ? "\n" . $settings->get('business_email') : '')) ?></td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
