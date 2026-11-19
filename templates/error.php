<?php /** @var int $status @var string $message */ ?>
<div class="wrap-narrow">
    <div class="page-head">
        <h1><?= $status === 404 ? 'Nicht gefunden' : 'Das hat nicht geklappt' ?></h1>
        <p><?= e($message) ?></p>
        <p><a class="button secondary" href="<?= e(url('/')) ?>">Zur Startseite</a></p>
    </div>
</div>
