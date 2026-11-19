<?php
/** @var string $content @var string $title */
use App\App;
use App\Core\Auth;
use App\Core\View;

$settings = App::settings();
$user = Auth::user();
?>
<!doctype html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? 'Verwaltung') . ' | ' . $settings->get('business_name')) ?></title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <style>:root { --accent: <?= e($settings->accent()) ?>; --accent-ink: <?= e($settings->accentInk()) ?>; }</style>
</head>
<body class="admin">
<?php if ($user): ?>
<header class="admin-bar">
    <div class="wrap">
        <a class="brand" href="<?= e(url('/admin')) ?>"><?= e($settings->get('business_name')) ?> <span>Gutscheine</span></a>
        <nav class="admin-nav" aria-label="Verwaltung">
            <?php if (Auth::isAdmin()): ?>
                <a href="<?= e(url('/admin')) ?>"<?= nav_active('/admin') ?>>Übersicht</a>
            <?php endif ?>
            <a href="<?= e(url('/admin/einloesen')) ?>"<?= nav_active('/admin/einloesen') ?>>Einlösen</a>
            <?php if (Auth::isAdmin()): ?>
                <a href="<?= e(url('/admin/gutscheine')) ?>"<?= nav_active('/admin/gutscheine') ?>>Gutscheine</a>
                <a href="<?= e(url('/admin/bestellungen')) ?>"<?= nav_active('/admin/bestellungen') ?>>Bestellungen</a>
                <a href="<?= e(url('/admin/produkte')) ?>"<?= nav_active('/admin/produkte') ?>>Produkte</a>
                <a href="<?= e(url('/admin/einstellungen')) ?>"<?= nav_active('/admin/einstellungen') ?>>Einstellungen</a>
                <a href="<?= e(url('/admin/benutzer')) ?>"<?= nav_active('/admin/benutzer') ?>>Benutzer</a>
            <?php endif ?>
        </nav>
        <div class="admin-user">
            <span><?= e($user['name']) ?></span>
            <form method="post" action="<?= e(url('/admin/logout')) ?>">
                <?= csrf_field() ?>
                <button class="button quiet" type="submit">Abmelden</button>
            </form>
        </div>
    </div>
</header>
<?php endif ?>
<main>
    <div class="wrap">
        <?= View::partial('partials/flash') ?>
        <?= $content ?>
    </div>
</main>
<script src="<?= e(asset('app.js')) ?>" defer></script>
</body>
</html>
