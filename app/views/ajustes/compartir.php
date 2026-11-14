<?php

$shareUrl = $shareUrl ?? (string) config('app.share_url', '');
$shareUrl = $shareUrl !== '' ? $shareUrl : (string) config('app.url', '');
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('settings.compartir.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('settings.compartir.subtitle')) ?></p>
    </div>

<script src="<?= asset_versioned('js/qrcode.js') ?>"></script>
</div>

<div class="surface-card text-center share-card">
    <div class="qr-box" id="shareQr" data-qr-url="<?= e($shareUrl) ?>"
         aria-label="<?= e(__('settings.compartir.qr_label')) ?>"></div>

    <p class="share-url mt-3 mb-2"><?= e($shareUrl) ?></p>
    <p class="small text-secondary mb-3" data-qr-error hidden>
        <?= e(__('settings.compartir.qr_error')) ?>
    </p>
    <p class="small text-secondary mb-3"><?= e(__('settings.compartir.hint')) ?></p>

    <button type="button" class="btn btn-primary" data-action="copy-link"
            data-copy-value="<?= e($shareUrl) ?>">
        <i class="bi bi-clipboard me-1" aria-hidden="true"></i>
        <?= e(__('settings.compartir.copy')) ?>
    </button>

    <p class="small text-success mt-2 mb-0" data-copy-feedback hidden>
        <?= e(__('settings.compartir.copied')) ?>
    </p>
</div>
