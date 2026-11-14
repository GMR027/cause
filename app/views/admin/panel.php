<?php
$userCount = (int) ($userCount ?? 0);
$auditCount = (int) ($auditCount ?? 0);
$recentAudit = $recentAudit ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= e(__('common.admin.welcome')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('common.admin.welcome_hint')) ?></p>
    </div>
    <a href="/admin/usuarios/crear" class="btn btn-primary"><?= e(__('common.admin.create_user')) ?></a>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-1"><?= e(__('common.admin.users')) ?></h2>
                <p class="display-6 mb-2"><?= $userCount ?></p>
                <p class="small text-secondary mb-3">
                    <?= e(__('common.admin.user_count', [':count' => (string) $userCount])) ?>
                </p>
                <a href="/admin/usuarios" class="btn btn-sm btn-outline-secondary"><?= e(__('common.actions.view')) ?></a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-1"><?= e(__('common.admin.audit')) ?></h2>
                <p class="display-6 mb-2"><?= $auditCount ?></p>
                <p class="small text-secondary mb-3">
                    <?= e(__('common.admin.audit_count', [':count' => (string) $auditCount])) ?>
                </p>
                <a href="/admin/auditoria" class="btn btn-sm btn-outline-secondary"><?= e(__('common.actions.view')) ?></a>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h2 class="h5 mb-3"><?= e(__('common.admin.recent_activity')) ?></h2>

        <?php if ($recentAudit === []): ?>
            <p class="text-secondary mb-0"><?= e(__('common.admin.audit_empty')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= e(__('common.admin.audit_date')) ?></th>
                            <th><?= e(__('common.admin.audit_admin')) ?></th>
                            <th><?= e(__('common.admin.audit_action')) ?></th>
                            <th><?= e(__('common.admin.audit_entity')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAudit as $entry): ?>
                            <tr>
                                <td class="small text-secondary"><?= e(format_datetime($entry['created_at'] ?? null)) ?></td>
                                <td><?= e($entry['admin_alias'] ?? '—') ?></td>
                                <td><?= e(audit_action_label((string) ($entry['accion'] ?? ''))) ?></td>
                                <td>
                                    <?= e($entry['entidad'] ?? '—') ?>
                                    <?php if (!empty($entry['entidad_id'])): ?>
                                        #<?= (int) $entry['entidad_id'] ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
