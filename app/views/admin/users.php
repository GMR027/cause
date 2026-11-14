<?php
$users = $users ?? [];
$recoveryCode = $recoveryCode ?? null;
$query = (string) ($query ?? '');
$total = (int) ($total ?? count($users));
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= e(__('common.admin.users')) ?></h1>
        <p class="text-secondary mb-0">
            <?= e(__('common.admin.user_count', [':count' => (string) $total])) ?>
        </p>
    </div>
    <a href="/admin/usuarios/crear" class="btn btn-primary"><?= e(__('common.admin.create_user')) ?></a>
</div>

<?php if (is_array($recoveryCode) && !empty($recoveryCode['code'])): ?>
    <div class="alert alert-warning" role="alert">
        <h2 class="h6"><?= e(__('common.admin.recovery_code')) ?></h2>
        <p class="small mb-2">
            <?= e(__('common.admin.recovery_code_hint', [':alias' => (string) ($recoveryCode['alias'] ?? '')])) ?>
        </p>
        <div class="recovery-code"><?= e((string) $recoveryCode['code']) ?></div>
    </div>
<?php endif; ?>

<form method="get" action="/admin/usuarios" class="surface-card mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-8">
            <label class="form-label" for="admin-user-search"><?= e(__('admin.users.search')) ?></label>
            <input type="search" class="form-control" id="admin-user-search" name="q"
                   value="<?= e($query) ?>" placeholder="<?= e(__('admin.users.search_placeholder')) ?>">
        </div>
        <div class="col-12 col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1"><?= e(__('admin.users.search_submit')) ?></button>
            <?php if ($query !== ''): ?>
                <a href="/admin/usuarios" class="btn btn-outline-secondary"><?= e(__('common.actions.clear')) ?></a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if ($users === []): ?>
            <p class="text-secondary p-4 mb-0"><?= e(__('common.admin.no_users')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0 admin-users-table">
                    <thead>
                        <tr>
                            <th><?= e(__('common.fields.alias')) ?></th>
                            <th><?= e(__('common.fields.email')) ?></th>
                            <th><?= e(__('common.admin.registered_at')) ?></th>
                            <th><?= e(__('admin.users.last_login')) ?></th>
                            <th><?= e(__('common.admin.state')) ?></th>
                            <th><?= e(__('admin.users.language_currency')) ?></th>
                            <th><?= e(__('common.admin.onboarding')) ?></th>
                            <th><?= e(__('common.admin.role')) ?></th>
                            <th><?= e(__('admin.users.statements')) ?></th>
                            <th class="text-end"><?= e(__('common.admin.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $state = (string) ($user['estado'] ?? 'activo');
                            $pdfCount = (int) ($user['pdf_count'] ?? 0);
                            $pdfBytes = (int) ($user['pdf_bytes'] ?? 0);
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= e((string) $user['alias']) ?></td>
                                <td class="small"><?= e((string) $user['email']) ?></td>
                                <td class="small text-secondary"><?= e(format_datetime($user['created_at'] ?? null)) ?></td>
                                <td class="small text-secondary">
                                    <?= !empty($user['last_login_at']) ? e(format_datetime($user['last_login_at'])) : '—' ?>
                                </td>
                                <td>
                                    <span class="badge <?= $state === 'activo' ? 'text-bg-success' : 'text-bg-danger' ?>">
                                        <?= e(__('admin.users.states.' . $state)) ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <?= e(strtoupper((string) $user['idioma'])) ?> · <?= e((string) $user['moneda_activa']) ?>
                                </td>
                                <td>
                                    <?= e(!empty($user['onboarding_completado']) ? __('common.yes') : __('common.no')) ?>
                                </td>
                                <td>
                                    <span class="badge text-bg-light border">
                                        <?= e(__('common.roles.' . ($user['rol'] ?? 'user'))) ?>
                                    </span>
                                </td>
                                <td class="small text-secondary">
                                    <?= (int) $pdfCount ?> · <?= e(format_bytes($pdfBytes)) ?>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/usuarios/ver?id=<?= (int) $user['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary">
                                        <?= e(__('common.actions.view')) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($pages > 1): ?>
    <nav class="mt-3" aria-label="<?= e(__('admin.users.pagination')) ?>">
        <ul class="pagination pagination-sm mb-0">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="/admin/usuarios?page=<?= $i ?>&q=<?= e(rawurlencode($query)) ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>
