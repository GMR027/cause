<?php
$entries = $entries ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= e(__('common.admin.audit')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('common.admin.audit_count', [':count' => (string) count($entries)])) ?></p>
    </div>
    <a href="/admin" class="btn btn-outline-secondary"><?= e(__('common.actions.back')) ?></a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <?php if ($entries === []): ?>
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
                            <th><?= e(__('common.admin.audit_ip')) ?></th>
                            <th><?= e(__('common.admin.audit_details')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
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
                                <td class="small text-secondary"><?= e($entry['ip'] ?? '—') ?></td>
                                <td class="small">
                                    <?php if (!empty($entry['detalles_json'])): ?>
                                        <code><?= e(mb_strimwidth((string) $entry['detalles_json'], 0, 90, '…')) ?></code>
                                    <?php else: ?>
                                        —
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
