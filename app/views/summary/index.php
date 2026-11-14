<?php

$summaries = $summaries ?? [];
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('summary.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('summary.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('summary.subtitle')) ?></p>
    </div>
</div>

<?php if ($summaries === []): ?>
    <div class="surface-card text-center py-5">
        <i class="bi bi-journal-text fs-2 text-secondary" aria-hidden="true"></i>
        <p class="mt-2 mb-1 fw-semibold"><?= e(__('summary.empty')) ?></p>
        <p class="small text-secondary mb-0"><?= e(__('summary.empty_hint')) ?></p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($summaries as $summary): ?>
            <?php
            $inicio = (string) $summary['periodo_inicio'];
            $fin = (string) $summary['periodo_fin'];
            $isUnread = empty($summary['leido_at']);
            ?>
            <div class="col-12 col-md-6 col-xl-4">
                <a class="summary-card <?= $isUnread ? 'is-unread' : '' ?>"
                   href="/resumen?id=<?= (int) $summary['id'] ?>">
                    <span class="d-flex align-items-center justify-content-between gap-2">
                        <span class="summary-period">
                            <?= e(format_date($inicio)) ?> – <?= e(format_date($fin)) ?>
                        </span>
                        <?php if ($isUnread): ?>
                            <span class="badge text-bg-primary"><?= e(__('notifications.new')) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="small text-secondary">
                        <?= e(__('summary.generated', [
                            ':date' => format_date($summary['generado_at'] ?? null),
                        ])) ?>
                    </span>
                    <span class="summary-cta">
                        <?= e(__('summary.view')) ?>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
