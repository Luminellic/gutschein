<?php
/** @var list<array<string, mixed>> $products */
use App\Services\Products;
?>
<div class="admin-head">
    <h1>Produkte</h1>
    <a class="button" href="<?= e(url('/admin/produkte/neu')) ?>">Neues Produkt</a>
</div>
<p class="muted" style="max-width:44rem">Produkte sind die Gutscheine, die im Shop angeboten werden: Wertgutscheine mit fixem oder frei wählbarem Betrag und Leistungsgutscheine.</p>

<div class="table-wrap">
    <?php if ($products === []): ?>
        <div class="empty">
            <p>Noch keine Produkte. Legen Sie das erste an, damit der Shop etwas anbietet.</p>
            <a class="button" href="<?= e(url('/admin/produkte/neu')) ?>">Neues Produkt</a>
        </div>
    <?php else: ?>
        <table>
            <thead><tr><th>Titel</th><th>Typ</th><th class="num">Preis</th><th>Gültigkeit</th><th>Im Shop</th><th class="num">Reihenfolge</th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><a href="<?= e(url('/admin/produkte/' . $p['id'])) ?>"><?= e($p['title']) ?></a></td>
                    <td><?= $p['type'] === 'service' ? 'Leistung' : (Products::isCustomAmount($p) ? 'Wert, frei' : 'Wert, fix') ?></td>
                    <td class="num"><?= e(Products::priceLabel($p)) ?></td>
                    <td><?= $p['validity_months'] ? (int) $p['validity_months'] . ' Monate' : 'Standard' ?></td>
                    <td><span class="badge <?= (int) $p['active'] ? 'active' : '' ?>"><?= (int) $p['active'] ? 'Sichtbar' : 'Ausgeblendet' ?></span></td>
                    <td class="num"><?= (int) $p['sort'] ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
