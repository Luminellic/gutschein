<?php
/**
 * @var list<array<string, mixed>> $vouchers
 * @var string $q @var string $status @var int $page @var int $pages @var int $total
 */
use App\Services\VoucherService;
use App\Support\Code;

$filters = ['' => 'Alle', 'active' => 'Gültig', 'redeemed' => 'Eingelöst', 'expired' => 'Abgelaufen', 'void' => 'Storniert'];
$query = static fn (array $extra): string => url('/admin/gutscheine') . '?' . http_build_query(array_filter(['q' => $q, 'status' => $status] + $extra));
?>
<div class="admin-head">
    <h1>Gutscheine</h1>
    <div class="actions" style="margin:0">
        <a class="button secondary" href="<?= e(url('/admin/export/gutscheine')) ?>">Als CSV exportieren</a>
        <a class="button" href="<?= e(url('/admin/gutscheine/neu')) ?>">Gutschein ausstellen</a>
    </div>
</div>

<form class="filters" method="get" action="<?= e(url('/admin/gutscheine')) ?>">
    <div class="field">
        <label for="q">Suche</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Code, Name oder E-Mail">
    </div>
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
    <?php if ($vouchers === []): ?>
        <div class="empty">
            <p><?= $q !== '' || $status !== '' ? 'Keine Gutscheine für diese Suche.' : 'Noch keine Gutscheine ausgestellt.' ?></p>
        </div>
    <?php else: ?>
        <table>
            <thead>
            <tr><th>Code</th><th>Gutschein</th><th>Für / Käufer:in</th><th class="num">Wert</th><th class="num">Restwert</th><th>Gültig bis</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($vouchers as $v): $s = VoucherService::status($v); ?>
                <tr>
                    <td><a class="code" href="<?= e(url('/admin/gutscheine/' . $v['id'])) ?>"><?= e(Code::format((string) $v['code'])) ?></a></td>
                    <td><?= e($v['title']) ?><?= $v['channel'] === 'manual' ? ' <span class="muted">(manuell)</span>' : '' ?></td>
                    <td><?= e($v['recipient_name'] ?: '–') ?><br><span class="muted"><?= e($v['buyer_name'] ?? '') ?></span></td>
                    <td class="num"><?= e(money((int) $v['initial_cents'], false)) ?></td>
                    <td class="num"><?= e(money((int) $v['balance_cents'], false)) ?></td>
                    <td><?= e(date_ch((string) $v['valid_until'])) ?></td>
                    <td><span class="badge <?= e($s) ?>"><?= e(VoucherService::STATUS_LABELS[$s]) ?></span></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
<?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Seiten">
        <?php if ($page > 1): ?><a href="<?= e($query(['seite' => $page - 1])) ?>">Vorherige Seite</a><?php endif ?>
        <span>Seite <?= $page ?> von <?= $pages ?> (<?= $total ?> Gutscheine)</span>
        <?php if ($page < $pages): ?><a href="<?= e($query(['seite' => $page + 1])) ?>">Nächste Seite</a><?php endif ?>
    </nav>
<?php endif ?>
