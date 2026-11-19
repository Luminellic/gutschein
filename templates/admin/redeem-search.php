<?php /** @var string $code */ ?>
<div class="redeem-search">
    <h1>Gutschein einlösen</h1>
    <p class="muted">Code vom Gutschein eintippen oder den QR-Code scannen. Bindestriche und Gross-/Kleinschreibung spielen keine Rolle.</p>
    <div class="panel">
        <form method="get" action="<?= e(url('/admin/einloesen')) ?>">
            <div class="field">
                <label for="code">Gutscheincode</label>
                <input class="code-input" type="text" id="code" name="code" value="<?= e($code) ?>"
                       placeholder="XXXX-XXXX-XXXX" autocomplete="off" autocapitalize="characters" spellcheck="false"
                       required autofocus data-code-input>
            </div>
            <div class="actions">
                <button class="button large" type="submit">Gutschein suchen</button>
                <button class="button secondary large" type="button" data-scan hidden>Mit Kamera scannen</button>
            </div>
            <div class="scan"><video data-scan-video hidden playsinline muted></video></div>
        </form>
    </div>
    <p class="muted" style="margin-top:1rem">Tipp: Der QR-Code lässt sich auch mit der normalen Handykamera scannen. Wer angemeldet ist, kommt so direkt zur Einlösung.</p>
</div>
