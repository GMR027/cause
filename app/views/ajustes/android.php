<?php

use Cauce\Services\AndroidAppService;

$latest = $latest ?? null;
$downloadUrl = $downloadUrl ?? '/app/android/descargar';
$fromApp = $fromApp ?? null;
$isAdmin = (bool) ($isAdmin ?? false);
$devices = $devices ?? [];
$outdated = $fromApp !== null && AndroidAppService::isOutdated($fromApp['version'], $latest);
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('settings.android.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('settings.android.subtitle')) ?></p>
    </div>

<script src="<?= asset_versioned('js/qrcode.js') ?>"></script>
</div>

<?php if ($latest === null): ?>
    <div class="surface-card">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-icon settings-icon-lg" aria-hidden="true"><i class="bi bi-android2"></i></span>
            <div>
                <h2 class="h5 mb-1"><?= e(__('settings.android.soon')) ?></h2>
                <p class="small text-secondary mb-0"><?= e(__('settings.android.soon_hint')) ?></p>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php if ($fromApp !== null): ?>
        <div class="alert <?= $outdated ? 'alert-warning' : 'alert-success' ?>" role="status">
            <?= e(__($outdated ? 'settings.android.in_app_outdated' : 'settings.android.in_app_ok', [
                ':installed' => $fromApp['version'],
                ':version' => (string) $latest['version'],
            ])) ?>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="surface-card h-100">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="settings-icon settings-icon-lg" aria-hidden="true"><i class="bi bi-android2"></i></span>
                    <div>
                        <h2 class="h5 mb-1"><?= e(__('settings.android.available', [':version' => (string) $latest['version']])) ?></h2>
                        <p class="small text-secondary mb-0"><?= e(__('settings.android.requirements')) ?></p>
                    </div>
                </div>

                <dl class="row small mb-3">
                    <dt class="col-5 col-md-4 text-secondary fw-normal"><?= e(__('settings.android.current_version')) ?></dt>
                    <dd class="col-7 col-md-8 mb-2"><code><?= e((string) $latest['version']) ?></code></dd>

                    <dt class="col-5 col-md-4 text-secondary fw-normal"><?= e(__('settings.android.published_at')) ?></dt>
                    <dd class="col-7 col-md-8 mb-2"><?= e(format_datetime((string) $latest['publicado_en'])) ?></dd>

                    <dt class="col-5 col-md-4 text-secondary fw-normal"><?= e(__('settings.android.size')) ?></dt>
                    <dd class="col-7 col-md-8 mb-2"><?= e(format_bytes((int) $latest['tamano'])) ?></dd>

                    <dt class="col-5 col-md-4 text-secondary fw-normal">SHA-256</dt>
                    <dd class="col-7 col-md-8 mb-0"><code class="small text-break"><?= e((string) $latest['sha256']) ?></code></dd>
                </dl>

                <?php if (trim((string) ($latest['notas'] ?? '')) !== ''): ?>
                    <h3 class="h6"><?= e(__('settings.android.notes')) ?></h3>
                    <p class="small mb-3" style="white-space: pre-line"><?= e((string) $latest['notas']) ?></p>
                <?php endif; ?>

                <a class="btn btn-primary" href="/app/android/descargar" download>
                    <i class="bi bi-download me-1" aria-hidden="true"></i><?= e(__('settings.android.download')) ?>
                </a>
                <p class="small text-secondary mt-3 mb-0"><?= e(__('settings.android.install_hint')) ?></p>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="surface-card text-center h-100">
                <h2 class="h6 mb-3"><?= e(__('settings.android.qr_title')) ?></h2>
                <div class="qr-box" data-qr-url="<?= e($downloadUrl) ?>"
                     aria-label="<?= e(__('settings.android.qr_title')) ?>"></div>
                <p class="small text-secondary mt-3 mb-0" data-qr-error hidden><?= e(__('settings.compartir.qr_error')) ?></p>
                <p class="share-url small mt-3 mb-0"><?= e($downloadUrl) ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($isAdmin): ?>
    <div class="surface-card mt-3">
        <?php if ($fromApp !== null): ?>
            <a class="btn btn-sm btn-outline-secondary float-end" href="http://tauri.localhost/index.html?servidor=1">
                <i class="bi bi-hdd-network me-1" aria-hidden="true"></i><?= e(__('settings.android.change_server')) ?>
            </a>
        <?php endif; ?>
        <h2 class="h5 mb-1"><?= e(__('settings.android.devices_title')) ?></h2>
        <p class="small text-secondary"><?= e(__('settings.android.devices_hint')) ?></p>

        <?php if ($devices === []): ?>
            <p class="small text-secondary mb-0"><?= e(__('settings.android.devices_empty')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle small mb-0">
                    <thead>
                        <tr>
                            <th><?= e(__('settings.android.device_user')) ?></th>
                            <th><?= e(__('settings.android.device_model')) ?></th>
                            <th><?= e(__('settings.android.device_version')) ?></th>
                            <th><?= e(__('settings.android.device_state')) ?></th>
                            <th><?= e(__('settings.android.device_last_seen')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $device): ?>
                            <?php $old = AndroidAppService::isOutdated((string) $device['version'], $latest); ?>
                            <tr>
                                <td>
                                    <?php if (!empty($device['alias'])): ?>
                                        <span class="fw-semibold"><?= e((string) $device['alias']) ?></span>
                                        <span class="d-block text-secondary"><?= e((string) $device['email']) ?></span>
                                    <?php else: ?>
                                        <span class="text-secondary"><?= e(__('settings.android.device_no_user')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e((string) ($device['modelo'] ?? '—')) ?>
                                    <?php if (!empty($device['version_android'])): ?>
                                        <span class="d-block text-secondary">Android <?= e((string) $device['version_android']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= e((string) $device['version']) ?></code></td>
                                <td>
                                    <span class="badge <?= $old ? 'text-bg-warning' : 'text-bg-success' ?>">
                                        <?= e(__($old ? 'settings.android.state_outdated' : 'settings.android.state_ok')) ?>
                                    </span>
                                </td>
                                <td class="text-secondary"><?= e(format_datetime((string) $device['ultima_vez'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
