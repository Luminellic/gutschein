<?php
/**
 * @var array<string, mixed> $voucher
 * @var array<string, mixed>|null $order
 * @var list<array<string, mixed>> $transactions
 * @var int $journalBalance
 */
use App\Services\OrderService;
use App\Services\VoucherService;
use App\Support\Code;

$status = VoucherService::status($voucher);
$isVoid = $voucher['status'] === 'void';
?>
<p class="muted"><a href="<?= e(url('/admin/gutscheine')) ?>">Alle Gutscheine</a></p>
<div class="admin-head">
    <div>
        <h1><span class="code"><?= e(Code::format((string) $voucher['code'])) ?></span></h1>
        <?php $typeLabel = $voucher['type'] === 'service' ? 'Leistungsgutschein' : 'Wertgutschein'; ?>
        <p class="muted" style="margin:0.25rem 0 0"><?= e($voucher['title'] === $typeLabel ? $typeLabel : $voucher['title'] . ', ' . $typeLabel) ?></p>
    </div>
    <div class="actions" style="margin:0">
        <a class="button secondary" href="<?= e(url('/g/' . $voucher['token'])) ?>" target="_blank" rel="noopener">Gutschein ansehen</a>
        <?php if ($status === 'active'): ?>
            <a class="button" href="<?= e(url('/admin/einloesen/' . $voucher['id'])) ?>">Einlösen</a>
        <?php endif ?>
    </div>
</div>

<?php if ($journalBalance !== (int) $voucher['balance_cents']): ?>
    <div class="notice error" role="alert">
        <p>Achtung: Der gespeicherte Restwert (<?= e(money((int) $voucher['balance_cents'])) ?>) weicht von der Summe der Buchungen (<?= e(money($journalBalance)) ?>) ab.</p>
    </div>
<?php endif ?>

<div class="split">
    <div>
        <div class="panel">
            <div class="balance">
                <span>Restwert</span>
                <strong><?= e(money((int) $voucher['balance_cents'])) ?></strong>
            </div>
            <dl class="facts">
                <dt>Status</dt><dd><span class="badge <?= e($status) ?>"><?= e(VoucherService::STATUS_LABELS[$status]) ?></span></dd>
                <dt>Ursprünglicher Wert</dt><dd><?= e(money((int) $voucher['initial_cents'])) ?></dd>
                <dt>Gültig bis</dt><dd><?= e(date_ch((string) $voucher['valid_until'])) ?></dd>
                <dt>Für</dt><dd><?= e($voucher['recipient_name'] ?: 'Keine Angabe') ?></dd>
                <dt>Käufer:in</dt><dd><?= e($voucher['buyer_name'] ?: 'Keine Angabe') ?><?= $voucher['buyer_email'] ? '<br><a href="mailto:' . e($voucher['buyer_email']) . '">' . e($voucher['buyer_email']) . '</a>' : '' ?></dd>
                <dt>Kanal</dt><dd><?= $voucher['channel'] === 'manual' ? 'Manuell ausgestellt' : 'Online gekauft' ?></dd>
                <?php if ($order): ?>
                    <dt>Zahlung</dt><dd><?= e(ucfirst((string) $order['provider'])) ?>, <?= e(OrderService::STATUS_LABELS[$order['status']] ?? $order['status']) ?><?= $order['paid_at'] ? ' am ' . e(date_ch((string) $order['paid_at'], true)) : '' ?><?= $order['provider_ref'] ? '<br><span class="muted">' . e($order['provider_ref']) . '</span>' : '' ?></dd>
                <?php endif ?>
                <?php if ($voucher['message']): ?><dt>Nachricht</dt><dd style="white-space:pre-line"><?= e($voucher['message']) ?></dd><?php endif ?>
                <dt>Ausgestellt</dt><dd><?= e(date_ch((string) $voucher['created_at'], true)) ?></dd>
            </dl>
        </div>

        <h2 style="margin-top:2rem; font-size:var(--step-1)">Buchungen</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Zeit</th><th>Buchung</th><th class="num">Betrag</th><th class="num">Restwert</th><th>Von</th></tr></thead>
                <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= e(date_ch((string) $t['created_at'], true)) ?></td>
                        <td><?= e(VoucherService::TX_LABELS[$t['type']] ?? $t['type']) ?><?= $t['note'] ? '<br><span class="muted">' . e($t['note']) . '</span>' : '' ?></td>
                        <td class="num"><?= e(money((int) $t['amount_cents'], false)) ?></td>
                        <td class="num"><?= e(money((int) $t['balance_after_cents'], false)) ?></td>
                        <td><?= e($t['user_name'] ?? 'Online') ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h2 style="font-size:var(--step-1)">Aktionen</h2>
        <?php if ($isVoid): ?>
            <p class="muted">Dieser Gutschein ist storniert. Es sind keine Aktionen mehr möglich.</p>
        <?php else: ?>
            <details class="action">
                <summary>Gültigkeit ändern</summary>
                <form method="post" action="<?= e(url('/admin/gutscheine/' . $voucher['id'] . '/gueltigkeit')) ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="valid_until">Gültig bis</label>
                        <input type="date" id="valid_until" name="valid_until" value="<?= e($voucher['valid_until']) ?>" required>
                    </div>
                    <button class="button" type="submit">Gültigkeit speichern</button>
                </form>
            </details>
            <details class="action">
                <summary>Korrektur buchen</summary>
                <form method="post" action="<?= e(url('/admin/gutscheine/' . $voucher['id'] . '/korrektur')) ?>">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="field">
                            <label for="direction">Richtung</label>
                            <select id="direction" name="direction">
                                <option value="plus">Gutschreiben (+)</option>
                                <option value="minus">Abziehen (−)</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="adj_amount">Betrag</label>
                            <div class="input-prefix"><span>CHF</span><input type="text" inputmode="decimal" id="adj_amount" name="amount" required></div>
                        </div>
                    </div>
                    <div class="field">
                        <label for="adj_note">Begründung</label>
                        <input type="text" id="adj_note" name="note" maxlength="255" required>
                        <span class="hint">Zum Beispiel: Fehleinlösung vom 12.11. rückgängig gemacht.</span>
                    </div>
                    <button class="button" type="submit">Korrektur buchen</button>
                </form>
            </details>
            <details class="action">
                <summary>Per E-Mail senden</summary>
                <form method="post" action="<?= e(url('/admin/gutscheine/' . $voucher['id'] . '/mail')) ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="mail_to">E-Mail</label>
                        <input type="email" id="mail_to" name="email" value="<?= e($order['recipient_email'] ?? $voucher['buyer_email'] ?? '') ?>" required>
                    </div>
                    <button class="button" type="submit">Gutschein senden</button>
                </form>
            </details>
            <details class="action">
                <summary>Stornieren</summary>
                <form method="post" action="<?= e(url('/admin/gutscheine/' . $voucher['id'] . '/storno')) ?>" data-confirm="Gutschein wirklich stornieren? Der Restwert verfällt und das lässt sich nicht rückgängig machen.">
                    <?= csrf_field() ?>
                    <p class="muted">Der Restwert wird auf 0 gesetzt. Eine allfällige Rückzahlung erfolgt separat, z.B. im Stripe-Dashboard.</p>
                    <div class="field">
                        <label for="void_note">Grund</label>
                        <input type="text" id="void_note" name="note" maxlength="255" required>
                    </div>
                    <button class="button danger" type="submit">Gutschein stornieren</button>
                </form>
            </details>
        <?php endif ?>
    </div>
</div>
