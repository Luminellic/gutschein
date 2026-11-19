<?php
/**
 * @var array<string, mixed> $product
 * @var array<string, mixed> $old
 * @var array<string, string> $errors
 * @var DateTimeImmutable $validUntil
 */
use App\App;
use App\Core\View;
use App\Services\Products;
use App\Support\Money;

$settings = App::settings();
$custom = Products::isCustomAmount($product);
$min = (int) ($product['min_cents'] ?? Products::DEFAULT_MIN);
$max = (int) ($product['max_cents'] ?? Products::DEFAULT_MAX);
$amountInput = (string) ($old['amount'] ?? ($custom ? '' : Money::toInput((int) $product['amount_cents'])));
$previewCents = $custom ? (Money::parse($amountInput) ?? $min) : (int) $product['amount_cents'];
$deliverTo = ($old['deliver_to'] ?? 'buyer') === 'recipient' ? 'recipient' : 'buyer';

// Vorschlagsbeträge innerhalb der Grenzen
$suggestions = array_values(array_filter([5000, 10000, 15000, 20000, 30000], static fn (int $c): bool => $c >= $min && $c <= $max));

$err = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<span class="error-text" id="err-' . e($key) . '">' . e($errors[$key]) . '</span>' : '';
};
$invalid = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="err-' . e($key) . '"' : '';
};
$val = static fn (string $key): string => e((string) ($old[$key] ?? ''));
?>
<div class="wrap">
    <p class="muted"><a href="<?= e(url('/')) ?>">Alle Gutscheine</a></p>
    <div class="page-head">
        <h1><?= e($product['title']) ?></h1>
        <?php if (!empty($product['description'])): ?><p><?= e($product['description']) ?></p><?php endif ?>
    </div>

    <?php if ($errors !== []): ?>
        <div class="notice error" role="alert"><p>Bitte die markierten Angaben prüfen.</p></div>
    <?php endif ?>

    <div class="buy">
        <form method="post" action="<?= e(url('/gutschein/' . $product['id'])) ?>" id="formular" novalidate data-buy-form>
            <?= csrf_field() ?>

            <?php if ($custom): ?>
                <h2>Betrag</h2>
                <?php if ($suggestions !== []): ?>
                    <div class="amount-options" data-amount-options>
                        <?php foreach ($suggestions as $s): ?>
                            <button type="button" data-amount="<?= e(Money::toInput($s)) ?>" aria-pressed="false"><?= e(Money::format($s, false)) ?></button>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
                <div class="field">
                    <label for="amount">Betrag in Franken</label>
                    <div class="input-prefix">
                        <span>CHF</span>
                        <input type="text" inputmode="decimal" id="amount" name="amount" value="<?= e($amountInput) ?>"
                               placeholder="<?= e(Money::toInput($min)) ?>" autocomplete="off" required<?= $invalid('amount') ?>
                               data-min="<?= $min ?>" data-max="<?= $max ?>">
                    </div>
                    <span class="hint">Zwischen <?= e(Money::format($min)) ?> und <?= e(Money::format($max)) ?></span>
                    <?= $err('amount') ?>
                </div>
            <?php else: ?>
                <input type="hidden" name="amount" value="<?= e($amountInput) ?>">
            <?php endif ?>

            <h2>Für wen ist der Gutschein?</h2>
            <div class="field">
                <label for="recipient_name">Name der beschenkten Person <span class="muted">(optional)</span></label>
                <input type="text" id="recipient_name" name="recipient_name" value="<?= $val('recipient_name') ?>" maxlength="160" autocomplete="off"<?= $invalid('recipient_name') ?>>
                <?= $err('recipient_name') ?>
            </div>
            <div class="field">
                <label for="message">Persönliche Nachricht <span class="muted">(optional)</span> <span class="counter" data-counter-for="message" aria-hidden="true"></span></label>
                <textarea id="message" name="message" maxlength="300" rows="3"<?= $invalid('message') ?>><?= $val('message') ?></textarea>
                <span class="hint">Erscheint auf dem Gutschein. Höchstens 300 Zeichen.</span>
                <?= $err('message') ?>
            </div>

            <fieldset>
                <legend>Zustellung</legend>
                <label class="choice">
                    <input type="radio" name="deliver_to" value="buyer"<?= $deliverTo === 'buyer' ? ' checked' : '' ?>>
                    <span>An mich, zum Ausdrucken oder Weiterleiten</span>
                </label>
                <label class="choice">
                    <input type="radio" name="deliver_to" value="recipient"<?= $deliverTo === 'recipient' ? ' checked' : '' ?>>
                    <span>Direkt per E-Mail an die beschenkte Person</span>
                </label>
            </fieldset>
            <div class="field" data-recipient-email>
                <label for="recipient_email">E-Mail der beschenkten Person</label>
                <input type="email" id="recipient_email" name="recipient_email" value="<?= $val('recipient_email') ?>" autocomplete="off"<?= $invalid('recipient_email') ?>>
                <span class="hint">Nur nötig bei direkter Zustellung. Sie erhalten eine Kopie.</span>
                <?= $err('recipient_email') ?>
            </div>

            <h2>Ihre Angaben</h2>
            <div class="field">
                <label for="buyer_name">Vor- und Nachname</label>
                <input type="text" id="buyer_name" name="buyer_name" value="<?= $val('buyer_name') ?>" autocomplete="name" required<?= $invalid('buyer_name') ?>>
                <?= $err('buyer_name') ?>
            </div>
            <div class="field">
                <label for="buyer_email">E-Mail</label>
                <input type="email" id="buyer_email" name="buyer_email" value="<?= $val('buyer_email') ?>" autocomplete="email" required<?= $invalid('buyer_email') ?>>
                <span class="hint">Dorthin senden wir den Gutschein bzw. die Kaufbestätigung.</span>
                <?= $err('buyer_email') ?>
            </div>

            <div class="field">
                <label class="choice">
                    <input type="checkbox" name="terms" value="1"<?= !empty($old['terms']) ? ' checked' : '' ?><?= $invalid('terms') ?>>
                    <span>Ich akzeptiere die Bedingungen für Gutscheine.</span>
                </label>
                <details class="muted">
                    <summary>Bedingungen lesen</summary>
                    <p style="white-space: pre-line"><?= e($settings->get('terms')) ?></p>
                </details>
                <?= $err('terms') ?>
            </div>

            <div class="total">
                <span>Total</span>
                <strong data-total><?= e(Money::format($previewCents)) ?></strong>
            </div>
            <div class="actions">
                <button class="button large block" type="submit">Weiter zur Zahlung</button>
            </div>
            <p class="pay-methods">Bezahlen mit TWINT, Kredit- oder Debitkarte. Den Gutschein erhalten Sie direkt nach der Zahlung.</p>
        </form>

        <aside class="buy-preview" aria-label="Vorschau">
            <?= View::partial('partials/ticket', [
                'issuer' => $settings->get('business_name'),
                'title' => $product['title'],
                'valueCents' => $previewCents,
                'type' => $product['type'],
                'serviceDescription' => $product['description'],
                'recipientName' => $old['recipient_name'] ?? null,
                'message' => $old['message'] ?? null,
                'code' => null,
                'validUntil' => $validUntil->format('d.m.Y'),
                'balanceCents' => null,
                'qrSrc' => null,
                'preview' => true,
            ]) ?>
            <p class="muted">Vorschau. Code und QR-Code entstehen nach der Zahlung.</p>
        </aside>
    </div>
</div>
