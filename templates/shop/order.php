<?php
/**
 * @var array<string, mixed> $order
 * @var array<string, mixed>|null $voucher
 * @var array<string, mixed>|null $product
 * @var bool $cancelled
 */
use App\Support\Money;

$sentTo = $order['deliver_to'] === 'recipient' && $order['recipient_email'] ? $order['recipient_email'] : $order['buyer_email'];
?>
<div class="wrap-narrow">
    <?php if ($voucher): ?>
        <div class="page-head">
            <h1>Vielen Dank für Ihren Kauf</h1>
            <p>Die Zahlung über <?= e(Money::format((int) $order['amount_cents'])) ?> ist bestätigt.
               Der Gutschein ist unterwegs an <strong><?= e($sentTo) ?></strong>.</p>
        </div>
        <div class="panel">
            <h2>Ihr Gutschein</h2>
            <p>Sie können den Gutschein jetzt schon öffnen, ausdrucken oder auf dem Handy speichern.</p>
            <p><a class="button" href="<?= e(url('/g/' . $voucher['token'])) ?>">Gutschein öffnen</a></p>
            <p class="muted">Keine E-Mail erhalten? Bitte den Spam-Ordner prüfen. Der Link oben funktioniert auch ohne E-Mail.</p>
        </div>
    <?php elseif ($cancelled): ?>
        <div class="page-head">
            <h1>Zahlung abgebrochen</h1>
            <p>Es wurde nichts belastet. Sie können die Zahlung erneut starten.</p>
        </div>
        <p><a class="button" href="<?= e(url('/gutschein/' . $order['product_id'])) ?>">Nochmals versuchen</a></p>
    <?php else: ?>
        <div class="page-head">
            <h1>Zahlung wird bestätigt</h1>
            <p>Das dauert meist nur wenige Sekunden. Diese Seite lädt sich selbst neu.</p>
        </div>
        <p class="muted">Bestellung über <?= e(Money::format((int) $order['amount_cents'])) ?> für <?= e($product['title'] ?? 'Gutschein') ?>.
           Falls sich nach einer Minute nichts tut, melden Sie sich bitte bei uns.</p>
    <?php endif ?>
</div>
