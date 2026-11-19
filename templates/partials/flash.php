<?php
use App\Core\Session;

foreach (Session::takeFlashes() as $flash): ?>
    <div class="notice <?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
        <p><?= e($flash['message']) ?></p>
    </div>
<?php endforeach;
