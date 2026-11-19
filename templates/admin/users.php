<?php
/** @var list<array<string, mixed>> $users @var array<string, mixed> $old */
use App\Core\Auth;
?>
<h1>Benutzer</h1>
<p class="muted" style="max-width:44rem"><strong>Kasse</strong> darf nur Gutscheine suchen und einlösen. <strong>Admin</strong> sieht alles und kann Produkte, Einstellungen und Benutzer verwalten.</p>

<div class="split">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Rolle</th><th>Letzte Anmeldung</th><th>Aktionen</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): $self = (int) $u['id'] === Auth::id(); ?>
                <tr>
                    <td><?= e($u['name']) ?><?= $self ? ' <span class="muted">(Sie)</span>' : '' ?><br><span class="muted"><?= e($u['email']) ?></span>
                        <?php if (!(int) $u['active']): ?><br><span class="badge void">Deaktiviert</span><?php endif ?></td>
                    <td><?= $u['role'] === 'admin' ? 'Admin' : 'Kasse' ?></td>
                    <td><?= e($u['last_login_at'] ? date_ch((string) $u['last_login_at'], true) : 'Noch nie') ?></td>
                    <td>
                        <?php if (!$self): ?>
                            <form method="post" action="<?= e(url('/admin/benutzer/' . $u['id'])) ?>" style="display:inline">
                                <?= csrf_field() ?>
                                <button class="button quiet" type="submit" name="action" value="role"><?= $u['role'] === 'admin' ? 'Zu Kasse machen' : 'Zu Admin machen' ?></button>
                                <button class="button quiet" type="submit" name="action" value="toggle"><?= (int) $u['active'] ? 'Deaktivieren' : 'Aktivieren' ?></button>
                            </form>
                        <?php endif ?>
                        <details>
                            <summary class="muted" style="cursor:pointer">Passwort setzen</summary>
                            <form method="post" action="<?= e(url('/admin/benutzer/' . $u['id'])) ?>" style="margin-top:0.5rem; display:flex; gap:0.5rem">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="password">
                                <label class="visually-hidden" for="pw-<?= (int) $u['id'] ?>">Neues Passwort</label>
                                <input type="password" id="pw-<?= (int) $u['id'] ?>" name="password" minlength="10" autocomplete="new-password" required placeholder="Neues Passwort">
                                <button class="button secondary" type="submit">Setzen</button>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <form class="panel" method="post" action="<?= e(url('/admin/benutzer')) ?>">
        <?= csrf_field() ?>
        <h2 style="font-size:var(--step-1)">Benutzer erfassen</h2>
        <div class="field">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?= e($old['name'] ?? '') ?>" required>
        </div>
        <div class="field">
            <label for="email">E-Mail</label>
            <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" autocomplete="off" required>
        </div>
        <div class="field">
            <label for="password">Passwort</label>
            <input type="password" id="password" name="password" minlength="10" autocomplete="new-password" required>
            <span class="hint">Mindestens 10 Zeichen.</span>
        </div>
        <div class="field">
            <label for="role">Rolle</label>
            <select id="role" name="role">
                <option value="staff"<?= ($old['role'] ?? 'staff') === 'staff' ? ' selected' : '' ?>>Kasse</option>
                <option value="admin"<?= ($old['role'] ?? '') === 'admin' ? ' selected' : '' ?>>Admin</option>
            </select>
        </div>
        <button class="button" type="submit">Benutzer erfassen</button>
    </form>
</div>
