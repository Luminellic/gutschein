<?php
/**
 * @var list<array<string, mixed>> $products
 * @var array<string, mixed> $old
 * @var array<string, string> $errors
 * @var string $defaultValidUntil
 */
use App\Support\Money;

$val = static fn (string $key, string $default = ''): string => e((string) ($old[$key] ?? $default));
$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="error-text">' . e($errors[$key]) . '</span>' : '';
?>
<p class="muted"><a href="<?= e(url('/admin/gutscheine')) ?>">Alle Gutscheine</a></p>
<h1>Gutschein ausstellen</h1>
<p class="muted" style="max-width:40rem">Für Verkäufe an der Theke, Kulanz oder Wettbewerbe. Der Gutschein wird sofort gültig und erscheint im Buchungsjournal.</p>

<form class="panel" style="max-width:44rem" method="post" action="<?= e(url('/admin/gutscheine/neu')) ?>">
    <?= csrf_field() ?>
    <div class="field">
        <label for="product_id">Vorlage</label>
        <select id="product_id" name="product_id">
            <option value="">Freier Wertgutschein</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= (int) $p['id'] ?>"<?= (string) ($old['product_id'] ?? '') === (string) $p['id'] ? ' selected' : '' ?>>
                    <?= e($p['title']) ?><?= $p['amount_cents'] !== null ? ' (' . e(Money::format((int) $p['amount_cents'])) . ')' : '' ?>
                </option>
            <?php endforeach ?>
        </select>
        <span class="hint">Mit einer Vorlage werden Typ und Titel übernommen. Der Betrag muss trotzdem angegeben werden.</span>
    </div>
    <div class="row">
        <div class="field">
            <label for="title">Titel</label>
            <input type="text" id="title" name="title" value="<?= $val('title') ?>" maxlength="160" placeholder="Wertgutschein">
            <span class="hint">Leer lassen, um den Titel der Vorlage zu verwenden.</span>
            <?= $err('title') ?>
        </div>
        <div class="field">
            <label for="amount">Betrag</label>
            <div class="input-prefix"><span>CHF</span><input type="text" inputmode="decimal" id="amount" name="amount" value="<?= $val('amount') ?>" required></div>
            <?= $err('amount') ?>
        </div>
    </div>
    <div class="row">
        <div class="field">
            <label for="valid_until">Gültig bis</label>
            <input type="date" id="valid_until" name="valid_until" value="<?= $val('valid_until', $defaultValidUntil) ?>" required>
            <?= $err('valid_until') ?>
        </div>
        <div class="field">
            <label for="recipient_name">Für <span class="muted">(optional)</span></label>
            <input type="text" id="recipient_name" name="recipient_name" value="<?= $val('recipient_name') ?>" maxlength="160">
        </div>
    </div>
    <div class="field">
        <label for="message">Nachricht auf dem Gutschein <span class="muted">(optional)</span></label>
        <textarea id="message" name="message" maxlength="300" rows="2"><?= $val('message') ?></textarea>
    </div>
    <div class="row">
        <div class="field">
            <label for="buyer_name">Käufer:in <span class="muted">(optional)</span></label>
            <input type="text" id="buyer_name" name="buyer_name" value="<?= $val('buyer_name') ?>" maxlength="160">
        </div>
        <div class="field">
            <label for="buyer_email">E-Mail <span class="muted">(optional)</span></label>
            <input type="email" id="buyer_email" name="buyer_email" value="<?= $val('buyer_email') ?>">
            <?= $err('buyer_email') ?>
        </div>
    </div>
    <label class="choice">
        <input type="checkbox" name="send_mail" value="1"<?= !empty($old['send_mail']) ? ' checked' : '' ?>>
        <span>Gutschein an diese E-Mail senden</span>
    </label>
    <div class="field" style="margin-top:1rem">
        <label for="note">Interne Notiz <span class="muted">(optional)</span></label>
        <input type="text" id="note" name="note" value="<?= $val('note') ?>" maxlength="255" placeholder="z.B. Bar bezahlt, Kassenbeleg 1043">
    </div>
    <div class="actions">
        <button class="button" type="submit">Gutschein ausstellen</button>
        <a class="button quiet" href="<?= e(url('/admin/gutscheine')) ?>">Abbrechen</a>
    </div>
</form>
