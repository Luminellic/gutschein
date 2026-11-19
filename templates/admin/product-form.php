<?php
/**
 * @var array<string, mixed>|null $product
 * @var array<string, mixed> $old
 * @var array<string, string> $errors
 */
use App\Support\Money;

$p = $product ?? [];
$hasOld = $old !== [];
$v = static function (string $key, string $fromProduct = '') use ($old, $hasOld): string {
    return e($hasOld ? (string) ($old[$key] ?? '') : $fromProduct);
};
$type = $hasOld ? ($old['type'] ?? 'value') : ($p['type'] ?? 'value');
$mode = $hasOld ? ($old['amount_mode'] ?? 'fixed') : (($p !== [] && $p['type'] === 'value' && $p['amount_cents'] === null) ? 'custom' : 'fixed');
$active = $hasOld ? !empty($old['active']) : (bool) ($p['active'] ?? true);
$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="error-text">' . e($errors[$key]) . '</span>' : '';
$action = $product ? url('/admin/produkte/' . $product['id']) : url('/admin/produkte/neu');
?>
<p class="muted"><a href="<?= e(url('/admin/produkte')) ?>">Alle Produkte</a></p>
<h1><?= $product ? 'Produkt bearbeiten' : 'Neues Produkt' ?></h1>

<form class="panel" style="max-width:44rem" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <fieldset>
        <legend>Art des Gutscheins</legend>
        <label class="choice">
            <input type="radio" name="type" value="value"<?= $type === 'value' ? ' checked' : '' ?>>
            <span><strong>Wertgutschein</strong><br><span class="muted">Ein Geldbetrag, kann in Teilen eingelöst werden.</span></span>
        </label>
        <label class="choice">
            <input type="radio" name="type" value="service"<?= $type === 'service' ? ' checked' : '' ?>>
            <span><strong>Leistungsgutschein</strong><br><span class="muted">Eine bestimmte Leistung, wird als Ganzes eingelöst.</span></span>
        </label>
    </fieldset>

    <div class="field">
        <label for="title">Titel</label>
        <input type="text" id="title" name="title" value="<?= $v('title', (string) ($p['title'] ?? '')) ?>" maxlength="160" required placeholder="z.B. Massage 60 Minuten">
        <?= $err('title') ?>
    </div>
    <div class="field">
        <label for="description">Beschreibung <span class="muted">(optional)</span></label>
        <textarea id="description" name="description" rows="3"><?= $v('description', (string) ($p['description'] ?? '')) ?></textarea>
        <span class="hint">Erscheint im Shop. Bei Leistungsgutscheinen auch auf dem Gutschein.</span>
    </div>

    <fieldset>
        <legend>Betrag</legend>
        <label class="choice">
            <input type="radio" name="amount_mode" value="fixed"<?= $mode === 'fixed' ? ' checked' : '' ?>>
            <span>Fixer Betrag bzw. Preis</span>
        </label>
        <label class="choice">
            <input type="radio" name="amount_mode" value="custom"<?= $mode === 'custom' ? ' checked' : '' ?>>
            <span>Frei wählbar innerhalb von Grenzen <span class="muted">(nur Wertgutschein)</span></span>
        </label>
    </fieldset>
    <div class="row">
        <div class="field">
            <label for="amount">Fixer Betrag</label>
            <div class="input-prefix"><span>CHF</span><input type="text" inputmode="decimal" id="amount" name="amount" value="<?= $v('amount', Money::toInput(isset($p['amount_cents']) ? (int) $p['amount_cents'] : null)) ?>"></div>
            <?= $err('amount') ?>
        </div>
        <div class="field">
            <label for="min">Mindestens</label>
            <div class="input-prefix"><span>CHF</span><input type="text" inputmode="decimal" id="min" name="min" value="<?= $v('min', Money::toInput(isset($p['min_cents']) ? (int) $p['min_cents'] : 2000)) ?>"></div>
            <?= $err('min') ?>
        </div>
        <div class="field">
            <label for="max">Höchstens</label>
            <div class="input-prefix"><span>CHF</span><input type="text" inputmode="decimal" id="max" name="max" value="<?= $v('max', Money::toInput(isset($p['max_cents']) ? (int) $p['max_cents'] : 50000)) ?>"></div>
            <?= $err('max') ?>
        </div>
    </div>

    <div class="row">
        <div class="field">
            <label for="validity_months">Gültigkeit in Monaten</label>
            <input type="number" id="validity_months" name="validity_months" min="1" max="120" value="<?= $v('validity_months', (string) ($p['validity_months'] ?? '')) ?>" placeholder="Standard">
            <span class="hint">Leer = Standard aus den Einstellungen.</span>
            <?= $err('validity_months') ?>
        </div>
        <div class="field">
            <label for="sort">Reihenfolge</label>
            <input type="number" id="sort" name="sort" value="<?= $v('sort', (string) ($p['sort'] ?? '0')) ?>">
            <span class="hint">Kleinere Zahlen erscheinen zuerst.</span>
        </div>
    </div>

    <label class="choice">
        <input type="checkbox" name="active" value="1"<?= $active ? ' checked' : '' ?>>
        <span>Im Shop anzeigen</span>
    </label>

    <div class="actions">
        <button class="button" type="submit">Produkt speichern</button>
        <a class="button quiet" href="<?= e(url('/admin/produkte')) ?>">Abbrechen</a>
    </div>
</form>
