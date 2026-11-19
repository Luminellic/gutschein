<?php
/**
 * @var array<string, string> $values
 * @var array<string, string> $errors
 * @var string $payment
 * @var string $mailDriver
 */
$v = static fn (string $key): string => e($values[$key] ?? '');
$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="error-text">' . e($errors[$key]) . '</span>' : '';
?>
<h1>Einstellungen</h1>

<div class="split">
    <form class="panel" method="post" action="<?= e(url('/admin/einstellungen')) ?>">
        <?= csrf_field() ?>
        <h2 style="font-size:var(--step-1)">Betrieb</h2>
        <div class="field">
            <label for="business_name">Name</label>
            <input type="text" id="business_name" name="business_name" value="<?= $v('business_name') ?>" required>
            <?= $err('business_name') ?>
        </div>
        <div class="field">
            <label for="business_address">Adresse</label>
            <textarea id="business_address" name="business_address" rows="3"><?= $v('business_address') ?></textarea>
        </div>
        <div class="row">
            <div class="field">
                <label for="business_email">E-Mail</label>
                <input type="email" id="business_email" name="business_email" value="<?= $v('business_email') ?>">
                <span class="hint">Antwortadresse für Gutschein-Mails.</span>
                <?= $err('business_email') ?>
            </div>
            <div class="field">
                <label for="business_phone">Telefon</label>
                <input type="text" id="business_phone" name="business_phone" value="<?= $v('business_phone') ?>">
            </div>
        </div>
        <div class="field">
            <label for="business_website">Website</label>
            <input type="url" id="business_website" name="business_website" value="<?= $v('business_website') ?>" placeholder="https://">
            <?= $err('business_website') ?>
        </div>
        <div class="row">
            <div class="field">
                <label for="imprint_url">Impressum (Link)</label>
                <input type="url" id="imprint_url" name="imprint_url" value="<?= $v('imprint_url') ?>" placeholder="https://">
                <?= $err('imprint_url') ?>
            </div>
            <div class="field">
                <label for="privacy_url">Datenschutz (Link)</label>
                <input type="url" id="privacy_url" name="privacy_url" value="<?= $v('privacy_url') ?>" placeholder="https://">
                <?= $err('privacy_url') ?>
            </div>
        </div>

        <h2 style="font-size:var(--step-1); margin-top:2rem">Shop und Gutschein</h2>
        <div class="field">
            <label for="shop_intro">Einleitung im Shop</label>
            <textarea id="shop_intro" name="shop_intro" rows="3"><?= $v('shop_intro') ?></textarea>
        </div>
        <div class="field">
            <label for="terms">Bedingungen</label>
            <textarea id="terms" name="terms" rows="4"><?= $v('terms') ?></textarea>
            <span class="hint">Müssen beim Kauf akzeptiert werden.</span>
        </div>
        <div class="row">
            <div class="field">
                <label for="accent_color">Akzentfarbe</label>
                <div style="display:flex; gap:0.5rem">
                    <input type="color" value="<?= $v('accent_color') ?>" aria-label="Farbe wählen" data-color-sync="accent_color">
                    <input type="text" id="accent_color" name="accent_color" value="<?= $v('accent_color') ?>" pattern="#[0-9A-Fa-f]{6}">
                </div>
                <span class="hint">Für Knöpfe und den Namen auf dem Gutschein.</span>
                <?= $err('accent_color') ?>
            </div>
            <div class="field">
                <label for="default_validity_months">Standard-Gültigkeit</label>
                <input type="number" id="default_validity_months" name="default_validity_months" min="1" max="120" value="<?= $v('default_validity_months') ?>">
                <span class="hint">In Monaten ab Kauf.</span>
                <?= $err('default_validity_months') ?>
            </div>
        </div>
        <div class="field">
            <label for="mail_from_name">Absendername für E-Mails <span class="muted">(optional)</span></label>
            <input type="text" id="mail_from_name" name="mail_from_name" value="<?= $v('mail_from_name') ?>" placeholder="<?= $v('business_name') ?>">
        </div>
        <div class="actions">
            <button class="button" type="submit">Einstellungen speichern</button>
        </div>
    </form>

    <div class="panel">
        <h2 style="font-size:var(--step-1)">Technik</h2>
        <p class="muted">Diese Werte stehen in der Datei <code>.env</code> auf dem Server.</p>
        <dl class="facts">
            <dt>Zahlung</dt><dd><?= $payment === 'stripe' ? 'Stripe (TWINT, Karte)' : 'Demo, Zahlungen werden simuliert' ?></dd>
            <dt>E-Mail</dt><dd><?= e(match ($mailDriver) { 'smtp' => 'SMTP', 'mail' => 'PHP mail()', default => 'Nur Protokoll, kein Versand' }) ?></dd>
        </dl>
        <p style="margin-top:1.5rem"><a href="<?= e(url('/admin/mails')) ?>">Mail-Protokoll ansehen</a></p>
        <p><a href="<?= e(url('/admin/export/buchungen')) ?>">Buchungen als CSV</a><br>
           <a href="<?= e(url('/admin/export/gutscheine')) ?>">Gutscheine als CSV</a></p>
    </div>
</div>
