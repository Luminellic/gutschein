<?php
/**
 * @var array<string, mixed> $voucher
 * @var list<array<string, mixed>> $transactions
 * @var int|null $done  eben eingelöster Betrag in Rappen
 */
use App\Core\Auth;
use App\Services\VoucherException;
use App\Services\VoucherService;
use App\Support\Code;

$status = VoucherService::status($voucher);
$balance = (int) $voucher['balance_cents'];
$reason = null;
try {
    \App\App::vouchers()->assertRedeemable($voucher);
} catch (VoucherException $e) {
    $reason = $e->getMessage();
}
$isService = $voucher['type'] === 'service';
?>
<div class="redeem-card">
    <p class="muted"><a href="<?= e(url('/admin/einloesen')) ?>">Anderen Gutschein suchen</a></p>

    <?php if ($done !== null): ?>
        <div class="notice success redeem-done" role="status">
            <p><strong><?= e(money((int) $done)) ?> eingelöst.</strong>
               <?= $balance > 0 ? 'Neuer Restwert: <strong>' . e(money($balance)) . '</strong>.' : 'Der Gutschein ist damit vollständig eingelöst.' ?></p>
            <p><a href="<?= e(url('/admin/einloesen')) ?>">Nächsten Gutschein einlösen</a></p>
        </div>
    <?php endif ?>

    <div class="panel">
        <div class="admin-head" style="margin-bottom:1rem">
            <div>
                <div class="code"><?= e(Code::format((string) $voucher['code'])) ?></div>
                <h1 style="margin:0.25rem 0 0; font-size:var(--step-2)"><?= e($voucher['title']) ?></h1>
            </div>
            <span class="badge <?= e($status) ?>"><?= e(VoucherService::STATUS_LABELS[$status]) ?></span>
        </div>

        <div class="balance">
            <span><?= $isService ? 'Leistung im Wert von' : 'Restwert' ?></span>
            <strong><?= e(money($isService ? (int) $voucher['initial_cents'] : $balance)) ?></strong>
        </div>

        <dl class="facts" style="margin-bottom:1.5rem">
            <?php if ($voucher['recipient_name']): ?><dt>Für</dt><dd><?= e($voucher['recipient_name']) ?></dd><?php endif ?>
            <dt>Gültig bis</dt><dd><?= e(date_ch((string) $voucher['valid_until'])) ?></dd>
            <?php if (!$isService): ?><dt>Ursprünglicher Wert</dt><dd><?= e(money((int) $voucher['initial_cents'])) ?></dd><?php endif ?>
            <?php if ($isService && $voucher['service_description']): ?><dt>Leistung</dt><dd><?= e($voucher['service_description']) ?></dd><?php endif ?>
        </dl>

        <?php if ($reason !== null): ?>
            <div class="notice error" role="alert"><p><?= e($reason) ?></p></div>
            <?php if (Auth::isAdmin()): ?>
                <a class="button secondary" href="<?= e(url('/admin/gutscheine/' . $voucher['id'])) ?>">Gutschein verwalten</a>
            <?php endif ?>
        <?php elseif ($isService): ?>
            <form method="post" action="<?= e(url('/admin/einloesen/' . $voucher['id'])) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="note">Notiz <span class="muted">(optional, z.B. Belegnummer)</span></label>
                    <input type="text" id="note" name="note" maxlength="255">
                </div>
                <button class="button large block" type="submit">Leistung einlösen</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('/admin/einloesen/' . $voucher['id'])) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="amount">Einzulösender Betrag</label>
                    <div class="input-prefix">
                        <span>CHF</span>
                        <input class="amount-input" type="text" inputmode="decimal" id="amount" name="amount" placeholder="0.00" autocomplete="off" required autofocus>
                    </div>
                    <span class="hint">Höchstens <?= e(money($balance)) ?></span>
                </div>
                <div class="field">
                    <label for="note">Notiz <span class="muted">(optional, z.B. Belegnummer)</span></label>
                    <input type="text" id="note" name="note" maxlength="255">
                </div>
                <button class="button large block" type="submit">Betrag einlösen</button>
            </form>
            <form method="post" action="<?= e(url('/admin/einloesen/' . $voucher['id'])) ?>" style="margin-top:0.75rem">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="all">
                <button class="button secondary block" type="submit">Ganzen Restwert einlösen (<?= e(money($balance)) ?>)</button>
            </form>
        <?php endif ?>
    </div>

    <?php if ($transactions !== []): ?>
        <h2 style="margin-top:2rem; font-size:var(--step-1)">Letzte Buchungen</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Zeit</th><th>Buchung</th><th class="num">Betrag</th><th class="num">Restwert</th></tr></thead>
                <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= e(date_ch((string) $t['created_at'], true)) ?></td>
                        <td><?= e(VoucherService::TX_LABELS[$t['type']] ?? $t['type']) ?><?= $t['note'] ? '<br><span class="muted">' . e($t['note']) . '</span>' : '' ?></td>
                        <td class="num"><?= e(money((int) $t['amount_cents'], false)) ?></td>
                        <td class="num"><?= e(money((int) $t['balance_after_cents'], false)) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>
