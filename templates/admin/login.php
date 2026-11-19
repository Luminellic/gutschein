<?php /** @var string $email */ ?>
<div class="login">
    <div class="panel">
        <h1>Anmelden</h1>
        <p class="muted"><?= e(\App\App::settings()->get('business_name')) ?>, Gutscheinverwaltung</p>
        <form method="post" action="<?= e(url('/admin/login')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="button block" type="submit">Anmelden</button>
        </form>
    </div>
</div>
