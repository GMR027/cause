<?php
$user = $user ?? [];
$sessionCount = (int) ($session_count ?? 0);
$statements = $statements ?? ['count' => 0, 'bytes' => 0];
$onboarding = $onboarding ?? [];
$hogar = $hogar ?? null;
$tempPassword = $tempPassword ?? null;
$exportLink = $exportLink ?? null;
$state = (string) ($user['estado'] ?? 'activo');
$isAdminTarget = ($user['rol'] ?? 'user') === 'admin';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <a href="/admin/usuarios" class="small text-decoration-none">← <?= e(__('common.admin.users')) ?></a>
        <h1 class="h3 mb-1 mt-1"><?= e((string) ($user['alias'] ?? '')) ?></h1>
        <p class="text-secondary mb-0"><?= e((string) ($user['email'] ?? '')) ?></p>
    </div>
    <span class="badge <?= $state === 'activo' ? 'text-bg-success' : 'text-bg-danger' ?>">
        <?= e(__('admin.users.states.' . $state)) ?>
    </span>
</div>

<?php if (is_array($tempPassword)): ?>
    <div class="alert alert-warning" role="alert">
        <h2 class="h6 mb-1"><?= e(__('admin.users.temp_password')) ?></h2>
        <p class="small mb-2">
            <?= e(__('admin.users.temp_password_hint', [':alias' => (string) ($tempPassword['alias'] ?? '')])) ?>
        </p>
        <div class="recovery-code"><?= e((string) ($tempPassword['password'] ?? '')) ?></div>
    </div>
<?php endif; ?>

<?php if (is_array($exportLink)): ?>
    <div class="alert alert-info" role="alert">
        <h2 class="h6 mb-1"><?= e(__('admin.users.export_link')) ?></h2>
        <p class="small mb-2">
            <?= e(__('admin.users.export_link_hint', [':alias' => (string) ($exportLink['alias'] ?? '')])) ?>
        </p>
        <div class="input-group">
            <input type="text" class="form-control" readonly
                   value="<?= e((string) config('app.url', '') . (string) ($exportLink['url'] ?? '')) ?>">
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-lg-7">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('admin.users.metadata')) ?></h2>
            <dl class="row small mb-0">
                <dt class="col-5 col-md-4 text-secondary fw-normal"><?= e(__('common.fields.alias')) ?></dt>
                <dd class="col-7 col-md-8"><?= e((string) ($user['alias'] ?? '')) ?></dd>

                <dt class="col-5 col-md-4 text-secondary fw-normal"><?= e(__('common.fields.email')) ?></dt>
                <dd class="col-7 col-md-8 text-break"><?= e((string) ($user['email'] ?? '')) ?></dd>

                <dt class="col-5 col-md-4 text-secondary fw-normal"><?= e(__('common.admin.registered_at')) ?></dt>
                <dd class="col-7 col-md-8"><?= e(format_datetime($user['created_at'] ?? null)) ?></dd>

                <dt class="col-5 col-md-4 text-secondary fw-normal"><?= e(__('admin.users.last_login')) ?></dt>
                <dd class="col-7 col-md-8">
                    <?= !empty($user['last_login_at']) ? e(format_datetime($user['last_login_at'])) : '—' ?>
                </dd>

                <dt class="col-5 col-md-4 text-secondary fw-normal"><?= e(__('common.admin.role')) ?></dt>
                <dd class="col-7 col-md-8"><?= e(__('common.roles.' . ($user['rol'] ?? 'user'))) ?></dd>

                <dt class="col-5 col-md-4 text-secondary fw-normal"><?= e(__('admin.users.language_currency')) ?></dt>
                <dd class="col-7 col-md-8">
                    <?= e(strtoupper((string) ($user['idioma'] ?? ''))) ?> ·
                    <?= e((string) ($user['moneda_activa'] ?? '')) ?>
                </dd>

                <dt class="col-5 col-md-4 text-secondary fw-normal"><?= e(__('common.admin.onboarding')) ?></dt>
                <dd class="col-7 col-md-8">
                    <?= e(!empty($user['onboarding_completado']) ? __('common.yes') : __('common.no')) ?>
                    · <?= e((string) ($onboarding['paso'] ?? '—')) ?>
                </dd>

                <dt class="col-5 col-md-4 text-secondary fw-normal"><?= e(__('admin.users.household')) ?></dt>
                <dd class="col-7 col-md-8">
                    <?php if (is_array($hogar)): ?>
                        <?= e((string) ($hogar['nombre'] ?? '')) ?>
                        (<?= e((string) ($hogar['rol'] ?? '')) ?>)
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>

                <?php if ($state === 'suspendido'): ?>
                    <dt class="col-5 col-md-4 text-secondary fw-normal"><?= e(__('admin.users.suspension_reason')) ?></dt>
                    <dd class="col-7 col-md-8"><?= e((string) ($user['suspendido_motivo'] ?? '')) ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('admin.users.storage')) ?></h2>
            <p class="small text-secondary mb-2">
                <?= e(__('admin.users.storage_hint')) ?>
            </p>
            <dl class="row small mb-3">
                <dt class="col-7 text-secondary fw-normal"><?= e(__('admin.users.statements')) ?></dt>
                <dd class="col-5 text-end mb-1"><?= (int) ($statements['count'] ?? 0) ?></dd>
                <dt class="col-7 text-secondary fw-normal"><?= e(__('admin.users.storage_used')) ?></dt>
                <dd class="col-5 text-end mb-1"><?= e(format_bytes((int) ($statements['bytes'] ?? 0))) ?></dd>
                <dt class="col-7 text-secondary fw-normal"><?= e(__('admin.users.sessions')) ?></dt>
                <dd class="col-5 text-end mb-0"><?= $sessionCount ?></dd>
            </dl>
            <p class="small text-secondary mb-0">
                <i class="bi bi-lock me-1" aria-hidden="true"></i>
                <?= e(__('admin.users.no_financial_data')) ?>
            </p>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('admin.users.actions_title')) ?></h2>

            <?php if ($state === 'activo'): ?>
                <form method="post" action="/admin/usuarios/suspender" class="mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                    <label class="form-label small" for="suspend-reason"><?= e(__('admin.users.suspension_reason')) ?></label>
                    <input type="text" class="form-control form-control-sm mb-2" id="suspend-reason"
                           name="motivo" maxlength="255" required>
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <?= e(__('admin.users.suspend')) ?>
                    </button>
                </form>
            <?php else: ?>
                <form method="post" action="/admin/usuarios/reactivar" class="mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-success">
                        <?= e(__('admin.users.reactivate')) ?>
                    </button>
                </form>
            <?php endif; ?>

            <form method="post" action="/admin/usuarios/cerrar-sesiones" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <?= e(__('admin.users.force_logout')) ?>
                </button>
            </form>

            <form method="post" action="/admin/usuarios/restablecer" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-warning">
                    <?= e(__('admin.users.reset_password')) ?>
                </button>
            </form>

            <form method="post" action="/admin/usuarios/exportar" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <?= e(__('admin.users.export_arco')) ?>
                </button>
                <p class="small text-secondary mt-2 mb-0"><?= e(__('admin.users.export_arco_hint')) ?></p>
            </form>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('admin.users.email_title')) ?></h2>
            <form method="post" action="/admin/usuarios/cambiar-email" class="mb-4">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                <label class="form-label small" for="admin-new-email"><?= e(__('common.fields.email')) ?></label>
                <div class="input-group input-group-sm mb-2">
                    <input type="email" class="form-control" id="admin-new-email" name="email"
                           value="<?= e((string) ($user['email'] ?? '')) ?>" required>
                    <button type="submit" class="btn btn-outline-primary"><?= e(__('common.actions.save')) ?></button>
                </div>
                <p class="small text-secondary mb-0"><?= e(__('admin.users.email_hint')) ?></p>
            </form>

            <h2 class="h5 mb-2 text-danger"><?= e(__('admin.users.delete_title')) ?></h2>
            <p class="small text-secondary mb-2"><?= e(__('admin.users.delete_hint')) ?></p>
            <form method="post" action="/admin/usuarios/eliminar" class="row g-2"
                  data-confirm="<?= e(__('privacy.delete.final_confirm')) ?>" data-confirm-danger>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                <div class="col-12">
                    <label class="form-label small" for="admin-delete-confirm">
                        <?= e(__('privacy.delete.type_word', [':word' => 'ELIMINAR'])) ?>
                    </label>
                    <input type="text" class="form-control form-control-sm" id="admin-delete-confirm"
                           name="confirmacion" autocomplete="off" required>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="admin-delete-check"
                               name="confirmo" value="1" required>
                        <label class="form-check-label small" for="admin-delete-check">
                            <?= e(__('privacy.delete.checkbox')) ?>
                        </label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-danger">
                        <?= e(__('admin.users.delete_submit')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
