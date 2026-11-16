<?php

$current = $current ?? null;
$currentSnapshot = $currentSnapshot ?? [];
$currentStatus = (string) ($currentStatus ?? '');
$plans = $plans ?? [];
$filters = $filters ?? ['estado' => '', 'momento' => ''];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
$hasMethod = (bool) ($hasMethod ?? true);
$currency = (string) ($currentUser['moneda_activa'] ?? 'MXN');
$statusClass = static fn (string $status): string => match ($status) {
    'completado' => 'text-bg-success',
    'vencido' => 'text-bg-secondary',
    default => 'text-bg-primary',
};
$statusLabel = static fn (string $status): string => __('planner.status.' . $status);
$query = static function (array $overrides = []) use ($filters, $page): string {
    $params = array_merge([
        'estado' => $filters['estado'] ?? '',
        'momento' => $filters['momento'] ?? '',
        'page' => (string) $page,
    ], $overrides);

    $params = array_filter($params, static fn ($value): bool => (string) $value !== '');

    return $params === [] ? '' : '?' . http_build_query($params);
};
$currentSummary = is_array($currentSnapshot['summary'] ?? null) ? $currentSnapshot['summary'] : [];
$currentUrl = $current !== null ? '/planeador/ver?id=' . (int) $current['id'] : '';
$currentIncomeUrl = $current !== null
    ? '/ingresos/nuevo?monto=' . rawurlencode(number_format((int) $current['monto_centavos'] / 100, 2, '.', ''))
        . '&moneda=' . rawurlencode((string) $current['moneda'])
        . '&plan_id=' . (int) $current['id']
    : '';
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('planner.index.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('planner.index.heading')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('planner.index.subtitle')) ?></p>
    </div>
    <?php if ($hasMethod): ?>
        <a class="btn btn-primary" href="/planeador/nuevo">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
            <?= e(__('planner.index.new_plan')) ?>
        </a>
    <?php endif; ?>
</div>

<?php if (!$hasMethod): ?>
    <div class="surface-card text-center py-5">
        <i class="bi bi-sliders fs-2 text-secondary" aria-hidden="true"></i>
        <p class="mt-2 mb-1 fw-semibold"><?= e(__('planner.index.no_method_title')) ?></p>
        <p class="small text-secondary mb-3"><?= e(__('planner.index.no_method_text')) ?></p>
        <a class="btn btn-primary" href="/ajustes/ingreso"><?= e(__('planner.index.no_method_action')) ?></a>
    </div>
<?php else: ?>
    <section class="mb-4">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h2 class="h5 mb-0"><?= e(__('planner.index.current_title')) ?></h2>
            <?php if ($current !== null): ?>
                <span class="badge planner-status <?= e($statusClass($currentStatus)) ?>">
                    <?= e($statusLabel($currentStatus)) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($current === null): ?>
            <div class="surface-card text-center py-4">
                <i class="bi bi-clipboard-check fs-2 text-secondary" aria-hidden="true"></i>
                <p class="mt-2 mb-1 fw-semibold"><?= e(__('planner.index.current_empty')) ?></p>
                <p class="small text-secondary mb-3"><?= e(__('planner.index.current_empty_hint')) ?></p>
                <a class="btn btn-primary" href="/planeador/nuevo"><?= e(__('planner.index.new_plan')) ?></a>
            </div>
        <?php else: ?>
            <div class="surface-card">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                    <div>
                        <h3 class="h6 mb-1">
                            <?= e(format_date((string) $current['fecha_ingreso'] . ' 12:00:00')) ?>
                        </h3>
                        <p class="small text-secondary mb-0">
                            <?= e(__('planner.index.saved_on', [':date' => format_datetime($current['created_at'] ?? null)])) ?>
                        </p>
                    </div>
                    <span class="fw-semibold">
                        <?= money_html((int) $current['monto_centavos'], (string) $current['moneda']) ?>
                    </span>
                </div>

                <?= partial('planner/partials/summary', [
                    'summary' => $currentSummary,
                    'currency' => $currency,
                    'days' => (int) ($currentSnapshot['dias_restantes'] ?? 0),
                ]) ?>

                <div class="d-flex flex-wrap gap-2 mt-2">
                    <a class="btn btn-outline-primary" href="<?= e($currentUrl) ?>">
                        <?= e(__('planner.index.view_plan')) ?>
                    </a>
                    <?php if ($currentStatus === 'activo'): ?>
                        <a class="btn btn-primary" href="<?= e($currentIncomeUrl) ?>">
                            <i class="bi bi-cash-coin me-1" aria-hidden="true"></i>
                            <?= e(__('planner.index.register_income')) ?>
                        </a>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" href="/planeador/nuevo?duplicar=<?= (int) $current['id'] ?>">
                        <i class="bi bi-copy me-1" aria-hidden="true"></i>
                        <?= e(__('planner.index.duplicate')) ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h2 class="h5 mb-0"><?= e(__('planner.index.history_title')) ?></h2>
            <span class="small text-secondary"><?= e(__('planner.index.total', [':count' => (string) $total])) ?></span>
        </div>

        <form method="get" action="/planeador" class="planner-filters mb-3">
            <div>
                <label class="form-label small mb-1" for="planner-filter-estado"><?= e(__('planner.index.filter_estado')) ?></label>
                <select class="form-select form-select-sm" id="planner-filter-estado" name="estado">
                    <option value=""><?= e(__('planner.index.all')) ?></option>
                    <option value="activo" <?= $filters['estado'] === 'activo' ? 'selected' : '' ?>><?= e(__('planner.status.activo')) ?></option>
                    <option value="completado" <?= $filters['estado'] === 'completado' ? 'selected' : '' ?>><?= e(__('planner.status.completado')) ?></option>
                </select>
            </div>
            <div>
                <label class="form-label small mb-1" for="planner-filter-momento"><?= e(__('planner.index.filter_momento')) ?></label>
                <select class="form-select form-select-sm" id="planner-filter-momento" name="momento">
                    <option value=""><?= e(__('planner.index.all')) ?></option>
                    <option value="proximos" <?= $filters['momento'] === 'proximos' ? 'selected' : '' ?>><?= e(__('planner.index.upcoming')) ?></option>
                    <option value="pasados" <?= $filters['momento'] === 'pasados' ? 'selected' : '' ?>><?= e(__('planner.index.past')) ?></option>
                </select>
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="submit">
                <i class="bi bi-funnel me-1" aria-hidden="true"></i>
                <?= e(__('planner.index.filter')) ?>
            </button>
        </form>

        <?php if ($plans === []): ?>
            <div class="surface-card text-center py-4">
                <p class="text-secondary mb-0"><?= e(__('planner.index.history_empty')) ?></p>
            </div>
        <?php else: ?>
            <div class="planner-history">
                <?php foreach ($plans as $plan): ?>
                    <?php $status = (string) ($plan['estado_visual'] ?? 'activo'); ?>
                    <a class="planner-history-row" href="/planeador/ver?id=<?= (int) $plan['id'] ?>">
                        <div>
                            <span class="fw-semibold d-block">
                                <?= e(format_date((string) $plan['fecha_ingreso'] . ' 12:00:00')) ?>
                            </span>
                            <span class="planner-history-meta">
                                <span class="badge planner-status <?= e($statusClass($status)) ?>">
                                    <?= e($statusLabel($status)) ?>
                                </span>
                                <?php if (!empty($plan['metodo_nombre'])): ?>
                                    <span><?= e((string) $plan['metodo_nombre']) ?></span>
                                <?php endif; ?>
                                <span><?= e(__('planner.index.saved_on', [':date' => format_datetime($plan['created_at'] ?? null)])) ?></span>
                            </span>
                        </div>
                        <span class="planner-history-amount">
                            <?= money_html((int) $plan['monto_centavos'], (string) $plan['moneda']) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="d-flex align-items-center justify-content-between gap-2 mt-3" aria-label="<?= e(__('planner.index.history_title')) ?>">
                <?php if ($page > 1): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="/planeador<?= e($query(['page' => (string) ($page - 1)])) ?>">
                        <?= e(__('planner.index.previous')) ?>
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <span class="small text-secondary">
                    <?= e(__('planner.index.pagination', [':page' => (string) $page, ':pages' => (string) $pages])) ?>
                </span>
                <?php if ($page < $pages): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="/planeador<?= e($query(['page' => (string) ($page + 1)])) ?>">
                        <?= e(__('planner.index.next')) ?>
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
<?php endif; ?>
