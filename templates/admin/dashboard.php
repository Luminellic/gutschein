<?php
/** @var array<string, int> $stats @var list<array<string, mixed>> $recent */
use App\Services\VoucherService;
use App\Support\Code;
?>
<div class="admin-head">
    <h1>Übersicht</h1>
    <div class="actions" style="margin:0">
        <a class="button secondary" href="<?= e(url('/admin/gutscheine/neu')) ?>">Gutschein ausstellen</a>
        <a class="button" href="<?= e(url('/admin/einloesen')) ?>">Gutschein einlösen</a>
    </div>
</div>

<dl class="figures">
    <div>
        <dt>Offener Restwert</dt>
        <dd><?= e(money($stats['open_sum'])) ?><small><?= (int) $stats['open_count'] ?> gültige Gutscheine</small></dd>
    </div>
    <div>
        <dt>Verkauft diesen Monat</dt>
        <dd><?= e(money($stats['month_sold'])) ?><small>Eingelöst: <?= e(money($stats['month_redeemed'])) ?></small></dd>
    </div>
    <div>
        <dt>Total ausgestellt</dt>
        <dd><?= e(money($stats['sold_sum'])) ?><small><?= (int) $stats['sold_count'] ?> Gutscheine</small></dd>
    </div>
    <div>
        <dt>Abgelaufen, nicht eingelöst</dt>
        <dd><?= e(money($stats['expired_sum'])) ?><small>Restwert abgelaufener Gutscheine</small></dd>
    </div>
</dl>

<div class="admin-head">
    <h2 style="margin:0">Letzte Buchungen</h2>
    <a href="<?= e(url('/admin/export/buchungen')) ?>">Alle Buchungen als CSV</a>
</div>
<div class="table-wrap">
    <?php if ($recent === []): ?>
        <div class="empty">
            <p>Noch keine Buchungen. Sobald Gutscheine verkauft oder eingelöst werden, erscheinen sie hier.</p>
            <a class="button secondary" href="<?= e(url('/')) ?>">Shop ansehen</a>
        </div>
    <?php else: ?>
        <table>
            <thead><tr><th>Zeit</th><th>Gutschein</th><th>Buchung</th><th class="num">Betrag</th><th class="num">Restwert</th><th>Von</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $t): ?>
                <tr>
                    <td class="num" style="text-align:left"><?= e(date_ch((string) $t['created_at'], true)) ?></td>
                    <td><a class="code" href="<?= e(url('/admin/gutscheine/' . $t['voucher_id'])) ?>"><?= e(Code::format((string) $t['code'])) ?></a><br><span class="muted"><?= e($t['title']) ?></span></td>
                    <td><?= e(VoucherService::TX_LABELS[$t['type']] ?? $t['type']) ?></td>
                    <td class="num"><?= e(money((int) $t['amount_cents'], false)) ?></td>
                    <td class="num"><?= e(money((int) $t['balance_after_cents'], false)) ?></td>
                    <td><?= e($t['user_name'] ?? 'Online') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
