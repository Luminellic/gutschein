<?php /** @var list<array<string, mixed>> $mails */ ?>
<h1>Mail-Protokoll</h1>
<p class="muted" style="max-width:44rem">Alle Gutschein-Mails der letzten Zeit. Im Demo-Modus (MAIL_DRIVER=log) werden Mails nicht verschickt, sondern nur hier abgelegt.</p>
<div class="table-wrap">
    <?php if ($mails === []): ?>
        <div class="empty"><p>Noch keine Mails.</p></div>
    <?php else: ?>
        <table>
            <thead><tr><th>Zeit</th><th>An</th><th>Betreff</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($mails as $m): ?>
                <tr>
                    <td><?= e(date_ch((string) $m['created_at'], true)) ?></td>
                    <td><?= e($m['recipient']) ?></td>
                    <td><a href="<?= e(url('/admin/mails/' . $m['id'])) ?>" target="_blank" rel="noopener"><?= e($m['subject']) ?></a></td>
                    <td>
                        <?php if ($m['status'] === 'failed'): ?>
                            <span class="badge void">Fehler</span><br><span class="muted"><?= e($m['error']) ?></span>
                        <?php elseif ($m['status'] === 'sent'): ?>
                            <span class="badge active">Gesendet</span>
                        <?php else: ?>
                            <span class="badge">Nur protokolliert</span>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
