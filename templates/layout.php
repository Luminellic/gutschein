<?php
/** @var string $content @var string $title */
use App\App;
use App\Core\Env;

$settings = App::settings();
$business = $settings->get('business_name');
?>
<!doctype html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Gutscheine') . ' | ' . $business) ?></title>
    <meta name="description" content="<?= e('Gutscheine von ' . $business . ' online kaufen und sofort per E-Mail erhalten.') ?>">
    <link rel="preload" href="<?= e(url('assets/fonts/hanken-grotesk-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <style>:root { --accent: <?= e($settings->accent()) ?>; --accent-ink: <?= e($settings->accentInk()) ?>; }</style>
    <?php if (!empty($refresh)): ?><meta http-equiv="refresh" content="<?= (int) $refresh ?>"><?php endif ?>
</head>
<body>
<?php if (Env::get('PAYMENT_PROVIDER', 'demo') === 'demo'): ?>
    <div class="demo-flag">Demo-Modus: Zahlungen werden nur simuliert.</div>
<?php endif ?>
<header class="site-header">
    <div class="wrap">
        <a class="brand" href="<?= e(url('/')) ?>"><?= e($business) ?></a>
        <?php if ($settings->get('business_website') !== ''): ?>
            <nav aria-label="Website"><a href="<?= e($settings->get('business_website')) ?>">Zur Website</a></nav>
        <?php endif ?>
    </div>
</header>
<main>
    <div class="wrap"><?= \App\Core\View::partial('partials/flash') ?></div>
    <?= $content ?>
</main>
<footer class="site-footer">
    <div class="wrap">
        <address><?= e($business . "\n" . $settings->get('business_address')) ?></address>
        <div>
            <?php if ($settings->get('business_email') !== ''): ?>
                <a href="mailto:<?= e($settings->get('business_email')) ?>"><?= e($settings->get('business_email')) ?></a><br>
            <?php endif ?>
            <?php if ($settings->get('business_phone') !== ''): ?>
                <?= e($settings->get('business_phone')) ?>
            <?php endif ?>
        </div>
        <ul>
            <?php if ($settings->get('imprint_url') !== ''): ?><li><a href="<?= e($settings->get('imprint_url')) ?>">Impressum</a></li><?php endif ?>
            <?php if ($settings->get('privacy_url') !== ''): ?><li><a href="<?= e($settings->get('privacy_url')) ?>">Datenschutz</a></li><?php endif ?>
        </ul>
    </div>
</footer>
<script src="<?= e(asset('app.js')) ?>" defer></script>
</body>
</html>
