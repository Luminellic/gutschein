<?php
/** @var list<array<string, mixed>> $orders @var string $status */
use App\Services\OrderService;
use App\Support\Code;

$filters = ['' => 'Alle', 'paid' => 'Bezahlt', 'pending' => 'Offen', 'cancelled' => 'Abgebrochen'];
?>
<div class="admin-head">
    <h1>Bestellungen</h1>
</div>
<p class="muted" style="max-width:44rem">Jeder Kaufversuch im Shop. Offene Bestellungen sind gestartete, aber (noch) nicht bezahlte Käufe. Erst bei bestätigter Zahlung entsteht ein Gutschein.</p>

<form class="filters" method="get" action="<?= e(url('/admin/bestellungen')) ?>">
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <?php foreach ($filters as $key => $label): ?>
                <option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <button class="button secondary" type="submit">Filtern</button>
</form>

<div class="table-wrap">
    <?php if ($orders === []): ?>
        <div class="empty"><p>Keine Bestellungen.</p></div>
    <?php else: ?>
        <table>
            <thead><tr><th>Datum</th><th>Produkt</th><th>Käufer:in</th><th class="num">Betrag</th><th>Zahlung</th><th>Status</th><th>Gutschein</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?= e(date_ch((string) $o['created_at'], true)) ?></td>
                    <td><?= e($o['product_title'] ?? '') ?></td>
                    <td><?= e($o['buyer_name']) ?><br><span class="muted"><?= e($o['buyer_email']) ?></span></td>
                    <td class="num"><?= e(money((int) $o['amount_cents'], false)) ?></td>
                    <td><?= e(ucfirst((string) $o['provider'])) ?></td>
                    <td><span class="badge <?= e($o['status']) ?>"><?= e(OrderService::STATUS_LABELS[$o['status']] ?? $o['status']) ?></span></td>
                    <td><?= $o['voucher_id'] ? '<a class="code" href="' . e(url('/admin/gutscheine/' . $o['voucher_id'])) . '">' . e(Code::format((string) $o['code'])) . '</a>' : '' ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
