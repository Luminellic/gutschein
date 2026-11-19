<?php
/** @var list<array<string, mixed>> $products */
use App\Services\Products;

$settings = \App\App::settings();
?>
<div class="wrap">
    <div class="page-head">
        <h1>Gutschein verschenken</h1>
        <p><?= e($settings->get('shop_intro')) ?></p>
    </div>

    <?php if ($products === []): ?>
        <div class="panel empty">
            <p>Im Moment sind keine Gutscheine im Angebot.</p>
        </div>
    <?php else: ?>
        <div class="product-list">
            <?php foreach ($products as $product): ?>
                <article class="ticket compact">
                    <div class="ticket-main">
                        <h2 class="ticket-title"><?= e($product['title']) ?></h2>
                        <?php if (!empty($product['description'])): ?>
                            <p><?= e($product['description']) ?></p>
                        <?php endif ?>
                        <a class="button" href="<?= e(url('/gutschein/' . $product['id'])) ?>">
                            Auswählen<span class="visually-hidden">: <?= e($product['title']) ?></span>
                        </a>
                    </div>
                    <div class="ticket-stub">
                        <?php if (Products::isCustomAmount($product)): ?>
                            <div class="price-note">Betrag frei wählbar</div>
                            <div class="price"><?= e(\App\Support\Money::format((int) ($product['min_cents'] ?? Products::DEFAULT_MIN))) ?></div>
                            <div class="price-note">bis <?= e(\App\Support\Money::format((int) ($product['max_cents'] ?? Products::DEFAULT_MAX))) ?></div>
                        <?php else: ?>
                            <div class="price-note"><?= $product['type'] === 'service' ? 'Leistung' : 'Wert' ?></div>
                            <div class="price"><?= e(\App\Support\Money::format((int) $product['amount_cents'])) ?></div>
                        <?php endif ?>
                    </div>
                </article>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
