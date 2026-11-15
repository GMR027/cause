<?php
$user = $user ?? [];
$statements = $statements ?? ['count' => 0, 'bytes' => 0];
$errors = $errors ?? [];
$analyticsOptOut = (int) ($user['analytics_opt_out'] ?? 0) === 1;
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('privacy.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('privacy.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('privacy.subtitle')) ?></p>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-lg-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-2"><?= e(__('privacy.export.title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('privacy.export.hint')) ?></p>

            <ul class="small text-secondary mb-3">
                <li><?= e(__('privacy.export.includes_csv')) ?></li>
                <li><?= e(__('privacy.export.includes_pdf')) ?></li>
                <li><?= e(__('privacy.export.includes_statements', [':count' => (string) ($statements['count'] ?? 0)])) ?></li>
            </ul>

            <form method="post" action="/ajustes/privacidad/exportar">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>
                    <?= e(__('privacy.export.submit')) ?>
                </button>
            </form>

            <p class="small text-secondary mt-3 mb-0">
                <i class="bi bi-shield-check me-1" aria-hidden="true"></i>
                <?= e(__('privacy.export.no_server_copy')) ?>
            </p>
            <p class="small text-secondary mb-0 mt-1">
                <i class="bi bi-envelope me-1" aria-hidden="true"></i>
                <?= e(__('privacy.export.email_unavailable')) ?>
            </p>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-2"><?= e(__('privacy.analytics.title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('privacy.analytics.hint')) ?></p>

            <form method="post" action="/ajustes/privacidad/analytics">
                <?= csrf_field() ?>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="analytics-opt-out"
                           name="opt_out" value="1" <?= $analyticsOptOut ? 'checked' : '' ?>>
                    <label class="form-check-label" for="analytics-opt-out">
                        <?= e(__('privacy.analytics.opt_out')) ?>
                    </label>
                </div>
                <p class="small text-secondary mb-3"><?= e(__('privacy.analytics.opt_out_hint')) ?></p>
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <?= e(__('common.actions.save')) ?>
                </button>
            </form>

            <hr class="my-4">

            <h2 class="h6 mb-2"><?= e(__('privacy.links.title')) ?></h2>
            <ul class="small mb-0">
                <li><a href="/privacidad"><?= e(__('legal.titles.privacidad')) ?></a></li>
                <li><a href="/terminos"><?= e(__('legal.titles.terminos')) ?></a></li>
                <li><a href="/aviso-legal"><?= e(__('legal.titles.aviso')) ?></a></li>
            </ul>
        </div>
    </div>
</div>

<div class="surface-card mb-4">
    <h2 class="h5 mb-2"><?= e(__('privacy.statements.title')) ?></h2>
    <p class="small text-secondary mb-3">
        <?= e(__('privacy.statements.hint', [
            ':count' => (string) ($statements['count'] ?? 0),
            ':size' => format_bytes((int) ($statements['bytes'] ?? 0)),
        ])) ?>
    </p>
    <a href="/estado-cuenta" class="btn btn-sm btn-outline-secondary">
        <?= e(__('statements.title')) ?>
    </a>
</div>

<div class="surface-card border-danger-subtle">
    <h2 class="h5 mb-2 text-danger"><?= e(__('privacy.delete.title')) ?></h2>
    <p class="small mb-2"><?= e(__('privacy.delete.warning')) ?></p>
    <p class="small text-secondary mb-3"><?= e(__('privacy.delete.backups')) ?></p>

    <form method="post" action="/ajustes/privacidad/eliminar" class="row g-3"
          data-confirm="<?= e(__('privacy.delete.final_confirm')) ?>" data-confirm-danger>
        <?= csrf_field() ?>
        <div class="col-12 col-md-5">
            <label class="form-label" for="delete-password"><?= e(__('common.fields.current_password')) ?></label>
            <input type="password" class="form-control" id="delete-password" name="password"
                   autocomplete="current-password" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="delete-confirmation">
                <?= e(__('privacy.delete.type_word', [':word' => 'ELIMINAR'])) ?>
            </label>
            <input type="text" class="form-control" id="delete-confirmation" name="confirmacion"
                   autocomplete="off" required>
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="delete-check" name="confirmo" value="1" required>
                <label class="form-check-label" for="delete-check">
                    <?= e(__('privacy.delete.checkbox')) ?>
                </label>
            </div>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-trash me-1" aria-hidden="true"></i>
                <?= e(__('privacy.delete.submit')) ?>
            </button>
        </div>
    </form>
</div>
