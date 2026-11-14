<?php
$conversations = $conversations ?? [];
$filter = (string) ($filter ?? 'todas');
$unread = (int) ($unread ?? 0);
$tabs = [
    'todas' => __('support.admin.filters.all'),
    'abiertas' => __('support.admin.filters.open'),
    'cerradas' => __('support.admin.filters.closed'),
];
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('support.admin.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('support.admin.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('support.admin.subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-start">
        <?php if ($unread > 0): ?>
            <span class="badge text-bg-primary"><?= e(__('support.unread', [':count' => (string) $unread])) ?></span>
        <?php endif; ?>
        <a href="/admin" class="btn btn-outline-secondary"><?= e(__('support.admin.back_admin')) ?></a>
    </div>
</div>

<div class="surface-card">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="/admin/soporte?filtro=<?= e($key) ?>"
               class="btn btn-sm <?= $filter === $key ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($conversations === []): ?>
        <div class="text-center py-4">
            <i class="bi bi-inbox fs-2 text-secondary" aria-hidden="true"></i>
            <p class="mt-2 mb-1 fw-semibold"><?= e(__('support.admin.empty')) ?></p>
            <p class="small text-secondary mb-0"><?= e(__('support.admin.empty_hint')) ?></p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= e(__('support.admin.user')) ?></th>
                        <th><?= e(__('support.admin.subject')) ?></th>
                        <th><?= e(__('support.admin.status')) ?></th>
                        <th class="text-end"><?= e(__('support.admin.unread')) ?></th>
                        <th><?= e(__('support.admin.updated')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conversations as $conversation): ?>
                        <tr class="<?= (int) ($conversation['no_leidos'] ?? 0) > 0 ? 'table-active' : '' ?>">
                            <td>
                                <span class="fw-semibold d-block"><?= e((string) $conversation['alias']) ?></span>
                                <span class="small text-secondary"><?= e((string) $conversation['email']) ?></span>
                            </td>
                            <td style="max-width: 22rem">
                                <span class="d-block text-truncate"><?= e((string) $conversation['asunto']) ?></span>
                                <span class="small text-secondary d-block text-truncate">
                                    <?= e((string) ($conversation['ultimo_mensaje'] ?? '')) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= (string) $conversation['estado'] === 'cerrada' ? 'text-bg-secondary' : 'text-bg-success' ?>">
                                    <?= e(__('support.status.' . (string) $conversation['estado'])) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if ((int) ($conversation['no_leidos'] ?? 0) > 0): ?>
                                    <span class="badge text-bg-primary"><?= (int) $conversation['no_leidos'] ?></span>
                                <?php else: ?>
                                    <span class="text-secondary">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary">
                                <?= e(format_datetime($conversation['ultimo_mensaje_at'] ?? $conversation['created_at'])) ?>
                            </td>
                            <td class="text-end">
                                <a href="/admin/soporte/ver?id=<?= (int) $conversation['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary">
                                    <?= e(__('support.admin.open')) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
