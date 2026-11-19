<?php
/** @var array<string, mixed> $order @var array<string, mixed>|null $product */
use App\Support\Money;
?>
<div class="wrap-narrow">
    <div class="page-head">
        <h1>Demo-Zahlung</h1>
        <p>Hier würde die Zahlungsseite von Stripe mit TWINT und Karte erscheinen. Im Demo-Modus wird die Zahlung nur simuliert, es fliesst kein Geld.</p>
    </div>
    <div class="panel">
        <dl class="facts">
            <dt>Gutschein</dt><dd><?= e($product['title'] ?? '') ?></dd>
            <dt>Betrag</dt><dd><?= e(Money::format((int) $order['amount_cents'])) ?></dd>
            <dt>E-Mail</dt><dd><?= e($order['buyer_email']) ?></dd>
        </dl>
        <form method="post" action="<?= e(url('/zahlung/demo/' . $order['public_id'])) ?>" class="actions">
            <?= csrf_field() ?>
            <button class="button large" type="submit" name="action" value="pay">Zahlung simulieren</button>
            <button class="button secondary large" type="submit" name="action" value="cancel">Abbrechen</button>
        </form>
    </div>
</div>
