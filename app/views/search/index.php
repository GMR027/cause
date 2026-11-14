<?php

$filters = $filters ?? [];
$results = $results ?? [];
$periods = $periods ?? [];
$categories = $categories ?? [];
$tags = $tags ?? [];
$hasQuery = (bool) ($hasQuery ?? false);
$total = (int) ($total ?? 0);
$currency = (string) ($currency ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$selectedPeriod = (($filters['periodo_inicio'] ?? '') !== '' && ($filters['periodo_fin'] ?? '') !== '')
    ? $filters['periodo_inicio'] . '|' . $filters['periodo_fin']
    : '';
$typeIcons = [
    'gasto' => 'bi-receipt',
    'ingreso' => 'bi-arrow-down-circle',
    'movimiento' => 'bi-arrow-left-right',
    'ahorro' => 'bi-piggy-bank',
    'inversion' => 'bi-graph-up-arrow',
    'donacion' => 'bi-heart',
    'meta' => 'bi-bullseye',
    'deuda' => 'bi-credit-card',
    'deseo' => 'bi-stars',
    'retiro_ahorro' => 'bi-piggy-bank',
    'retiro_inversion' => 'bi-graph-up-arrow',
    'pago_deuda' => 'bi-cash-coin',
];
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('search.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('search.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('search.subtitle')) ?></p>
    </div>
</div>

<div class="surface-card mb-4">
    <form method="get" action="/buscar" class="row g-3">
        <div class="col-12">
            <label class="form-label" for="search-q"><?= e(__('search.title')) ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input type="search" class="form-control" id="search-q" name="q"
                       value="<?= e((string) ($filters['q'] ?? '')) ?>"
                       placeholder="<?= e(__('search.placeholder')) ?>" autofocus>
                <button type="submit" class="btn btn-primary"><?= e(__('search.filters.apply')) ?></button>
            </div>
        </div>

        <div class="col-12">
            <hr class="my-1">
            <span class="small fw-semibold text-secondary"><?= e(__('search.filters.title')) ?></span>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="search-period"><?= e(__('search.filters.period')) ?></label>
            <select class="form-select" id="search-period" name="periodo">
                <option value=""><?= e(__('search.filters.all_periods')) ?></option>
                <?php foreach ($periods as $period): ?>
                    <?php $value = (string) $period['periodo_inicio'] . '|' . (string) $period['periodo_fin']; ?>
                    <option value="<?= e($value) ?>" <?= $selectedPeriod === $value ? 'selected' : '' ?>>
                        <?= e(format_date($period['periodo_inicio'] ?? null)) ?>
                        – <?= e(format_date($period['periodo_fin'] ?? null)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="search-from"><?= e(__('search.filters.from')) ?></label>
            <input type="date" class="form-control" id="search-from" name="desde"
                   value="<?= e((string) input('desde', '')) ?>">
        </div>

        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="search-to"><?= e(__('search.filters.to')) ?></label>
            <input type="date" class="form-control" id="search-to" name="hasta"
                   value="<?= e((string) input('hasta', '')) ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-2">
            <label class="form-label" for="search-category"><?= e(__('search.filters.category')) ?></label>
            <select class="form-select" id="search-category" name="parte">
                <option value=""><?= e(__('search.filters.all_categories')) ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['nombre']) ?>"
                            <?= (string) ($filters['categoria'] ?? '') === (string) $category['nombre'] ? 'selected' : '' ?>>
                        <?= e((string) $category['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="search-tag"><?= e(__('search.filters.tag')) ?></label>
            <select class="form-select" id="search-tag" name="tag">
                <option value=""><?= e(__('search.filters.all_tags')) ?></option>
                <?php foreach ($tags as $tag): ?>
                    <option value="<?= (int) $tag['id'] ?>"
                            <?= (int) ($filters['tag_id'] ?? 0) === (int) $tag['id'] ? 'selected' : '' ?>>
                        <?= e((string) $tag['nombre_normalizado']) ?> (<?= (int) $tag['usos'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-md-4 col-xl-2">
            <label class="form-label" for="search-medium"><?= e(__('search.filters.medium')) ?></label>
            <select class="form-select" id="search-medium" name="medio">
                <option value=""><?= e(__('search.filters.all_mediums')) ?></option>
                <?php foreach (['efectivo', 'debito', 'credito', 'transferencia', 'otro'] as $medium): ?>
                    <option value="<?= e($medium) ?>"
                            <?= (string) ($filters['medio'] ?? '') === $medium ? 'selected' : '' ?>>
                        <?= e(__('expenses.mediums.' . $medium)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label" for="search-min"><?= e(__('search.filters.min')) ?></label>
            <input type="text" inputmode="decimal" class="form-control" id="search-min" name="monto_min"
                   value="<?= isset($filters['monto_min']) ? e(number_format((int) $filters['monto_min'] / 100, 2, '.', '')) : '' ?>"
                   placeholder="0.00">
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label" for="search-max"><?= e(__('search.filters.max')) ?></label>
            <input type="text" inputmode="decimal" class="form-control" id="search-max" name="monto_max"
                   value="<?= isset($filters['monto_max']) ? e(number_format((int) $filters['monto_max'] / 100, 2, '.', '')) : '' ?>"
                   placeholder="0.00">
        </div>

        <div class="col-12 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary"><?= e(__('search.filters.apply')) ?></button>
            <a href="/buscar" class="btn btn-outline-secondary"><?= e(__('search.filters.clear')) ?></a>
        </div>
    </form>
</div>

<?php if (!$hasQuery): ?>
    <div class="surface-card text-center py-5">
        <i class="bi bi-search fs-2 text-secondary" aria-hidden="true"></i>
        <p class="mt-2 mb-1 fw-semibold"><?= e(__('search.prompt')) ?></p>
        <p class="small text-secondary mb-0"><?= e(__('search.prompt_hint')) ?></p>
    </div>
<?php elseif ($results === []): ?>
    <div class="surface-card text-center py-5">
        <i class="bi bi-inbox fs-2 text-secondary" aria-hidden="true"></i>
        <p class="mt-2 mb-1 fw-semibold"><?= e(__('search.empty')) ?></p>
        <p class="small text-secondary mb-0"><?= e(__('search.empty_hint')) ?></p>
    </div>
<?php else: ?>
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
        <p class="small text-secondary mb-0">
            <?= e(__('search.results', [':count' => (string) count($results)])) ?>
        </p>
        <p class="small text-secondary mb-0">
            <?= e(__('search.total')) ?>: <?= money_html($total, $currency) ?>
        </p>
    </div>

    <div class="surface-card">
        <div class="search-results">
            <?php foreach ($results as $result): ?>
                <?php $type = (string) ($result['tipo'] ?? 'gasto'); ?>
                <a class="search-result" href="<?= e((string) ($result['url'] ?? '/inicio')) ?>">
                    <span class="search-result-icon" aria-hidden="true">
                        <i class="bi <?= e($typeIcons[$type] ?? 'bi-dot') ?>"></i>
                    </span>
                    <span class="search-result-body">
                        <span class="search-result-title">
                            <?= e((string) ($result['titulo'] ?? '')) ?>
                            <span class="badge text-bg-light border ms-1"><?= e(__('search.types.' . $type)) ?></span>
                        </span>
                        <?php if (($result['subtitulo'] ?? '') !== ''): ?>
                            <span class="small text-secondary d-block"><?= e((string) $result['subtitulo']) ?></span>
                        <?php endif; ?>
                        <span class="small text-secondary">
                            <?= e(format_date($result['fecha'] ?? null)) ?>
                        </span>
                    </span>
                    <span class="search-result-amount">
                        <?= money_html((int) ($result['monto_centavos'] ?? 0), (string) ($result['moneda'] ?? 'MXN')) ?>
                    </span>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
