<?php

$version = $version ?? (string) config('app.version', '0.0.0');
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('settings.android.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('settings.android.subtitle')) ?></p>
    </div>
</div>

<div class="surface-card">
    <div class="d-flex align-items-center gap-3 mb-4">
        <span class="settings-icon settings-icon-lg" aria-hidden="true"><i class="bi bi-android2"></i></span>
        <div>
            <h2 class="h5 mb-1"><?= e(__('settings.android.soon')) ?></h2>
            <p class="small text-secondary mb-0"><?= e(__('settings.android.soon_hint')) ?></p>
        </div>
    </div>

    <dl class="row small mb-4">
        <dt class="col-6 col-md-4 text-secondary fw-normal"><?= e(__('settings.android.current_version')) ?></dt>
        <dd class="col-6 col-md-8 mb-2"><code><?= e($version) ?></code></dd>

        <dt class="col-6 col-md-4 text-secondary fw-normal"><?= e(__('settings.android.channel')) ?></dt>
        <dd class="col-6 col-md-8 mb-0"><?= e(__('settings.android.channel_value')) ?></dd>
    </dl>

    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-secondary" data-action="check-updates"
                data-endpoint="/api/app/version">
            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i><?= e(__('settings.android.check')) ?>
        </button>
        <button type="button" class="btn btn-primary" disabled>
            <i class="bi bi-download me-1" aria-hidden="true"></i><?= e(__('settings.android.download')) ?>
        </button>
    </div>

    <p class="small text-secondary mt-3 mb-0" data-update-status aria-live="polite"></p>
</div>
