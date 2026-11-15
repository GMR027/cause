<?php

$rows = $rows ?? [];
$tagMap = $tagMap ?? [];
$tags = $tags ?? [];
$periods = $periods ?? [];
$categories = $categories ?? [];
$subcategories = $subcategories ?? [];
$filters = $filters ?? [];
$periodoValue = $periodoValue ?? 'todos';
$feed = $feed ?? [];
$kind = (string) ($kind ?? 'todos');
$kinds = $kinds ?? [];
$totals = $totals ?? ['entradas' => 0, 'salidas' => 0];
$kindIcons = [
    'ingreso' => 'bi-arrow-down-circle',
    'ahorro' => 'bi-piggy-bank',
    'inversion' => 'bi-graph-up-arrow',
    'donacion' => 'bi-heart',
    'compartir' => 'bi-people',
    'movimiento' => 'bi-arrow-left-right',
];
$signs = ['entrada' => '+', 'salida' => '−', 'neutral' => ''];
$currency = (string) ($currency ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$stateClasses = [
    'asignado' => 'text-bg-success',
    'pendiente_asignacion' => 'text-bg-warning',
    'no_controlado' => 'text-bg-warning',
    'cerrado' => 'text-bg-secondary',
];
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('nav.movimientos')) ?></h1>
        <p class="text-secondary mb-0">
            <?= e(__('expenses.list.subtitle')) ?>
        </p>
        <p class="small mb-0">
            <?= e(__('expenses.list.income_total')) ?>: <strong class="text-success"><?= money_html((int) $totals['entradas'], $currency) ?></strong>
            · <?= e(__('expenses.list.outflow_total')) ?>: <strong><?= money_html((int) $totals['salidas'], $currency) ?></strong>
        </p>
    </div>
    <a href="/gastos/nuevo" class="btn btn-primary d-none d-xl-inline-flex">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('nav.fab.gasto')) ?>
    </a>
</div>

<form method="get" action="/movimientos" class="surface-card mb-3 filter-card">
    <div class="row g-2 align-items-end filter-grid">
        <div class="col-12 col-lg-2 filter-search">
            <label class="form-label small" for="q"><?= e(__('expenses.list.search')) ?></label>
            <input type="search" class="form-control" id="q" name="q"
                   value="<?= e((string) ($filters['q'] ?? '')) ?>"
                   placeholder="<?= e(__('expenses.list.search_placeholder')) ?>">
        </div>

        <div class="col-6 col-lg-2 filter-periodo">
            <label class="form-label small" for="periodo"><?= e(__('expenses.list.period')) ?></label>
            <select class="form-select" id="periodo" name="periodo">
                <option value="todos" <?= $periodoValue === 'todos' ? 'selected' : '' ?>><?= e(__('expenses.list.all_periods')) ?></option>
                <?php foreach ($periods as $period): ?>
                    <?php $value = $period['periodo_inicio'] . '|' . $period['periodo_fin']; ?>
                    <option value="<?= e($value) ?>" <?= $periodoValue === $value ? 'selected' : '' ?>>
                        <?= e(format_date($period['periodo_inicio'])) ?> – <?= e(format_date($period['periodo_fin'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-lg-1">
            <label class="form-label small" for="tipo"><?= e(__('expenses.list.kind')) ?></label>
            <select class="form-select" id="tipo" name="tipo">
                <?php foreach (array_merge(['todos'], $kinds) as $option): ?>
                    <option value="<?= e((string) $option) ?>" <?= $kind === $option ? 'selected' : '' ?>>
                        <?= e(__('expenses.list.kinds.' . (string) $option)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-lg-1">
            <label class="form-label small" for="parte"><?= e(__('expenses.list.part')) ?></label>
            <select class="form-select" id="parte" name="parte">
                <option value=""><?= e(__('expenses.list.all_parts')) ?></option>
                <?php foreach ($categories as $category): ?>
                    <?php $name = (string) $category['nombre']; ?>
                    <option value="<?= e($name) ?>" <?= ($filters['parte_metodo'] ?? '') === $name ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-lg-2">
            <label class="form-label small" for="subcategoria"><?= e(__('expenses.list.subcategory')) ?></label>
            <select class="form-select" id="subcategoria" name="subcategoria">
                <option value=""><?= e(__('expenses.list.all_subcategories')) ?></option>
                <option value="0" <?= (string) ($filters['subcategoria_id'] ?? '') === '0' ? 'selected' : '' ?>>
                    <?= e(__('expenses.show.uncategorized')) ?>
                </option>
                <?php foreach ($subcategories as $subcategory): ?>
                    <option value="<?= (int) $subcategory['id'] ?>"
                            <?= (string) ($filters['subcategoria_id'] ?? '') === (string) $subcategory['id'] ? 'selected' : '' ?>>
                        <?= e((string) $subcategory['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-lg-1">
            <label class="form-label small" for="tag"><?= e(__('expenses.list.tag')) ?></label>
            <select class="form-select" id="tag" name="tag">
                <option value=""><?= e(__('expenses.list.all_tags')) ?></option>
                <?php foreach ($tags as $tag): ?>
                    <option value="<?= (int) $tag['id'] ?>" <?= (string) ($filters['tag_id'] ?? '') === (string) $tag['id'] ? 'selected' : '' ?>>
                        #<?= e((string) $tag['nombre_normalizado']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-lg-1">
            <label class="form-label small" for="medio"><?= e(__('expenses.list.medium')) ?></label>
            <select class="form-select" id="medio" name="medio">
                <option value=""><?= e(__('expenses.list.all_mediums')) ?></option>
                <?php foreach (['efectivo', 'debito', 'credito', 'transferencia', 'otro'] as $medium): ?>
                    <option value="<?= e($medium) ?>" <?= ($filters['medio_pago'] ?? '') === $medium ? 'selected' : '' ?>>
                        <?= e(__('expenses.mediums.' . $medium)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-lg-1 d-flex gap-2 filter-actions">
            <button type="submit" class="btn btn-primary flex-grow-1"><?= e(__('expenses.list.filter')) ?></button>
            <a href="/movimientos" class="btn btn-outline-secondary"><?= e(__('expenses.list.clear')) ?></a>
        </div>
    </div>
    <p class="small text-secondary mt-2 mb-0"><?= e(__('expenses.list.expense_only_hint')) ?></p>
</form>

<?php if ($feed === []): ?>
    <div class="surface-card">
        <div class="empty-state">
            <span class="empty-icon" aria-hidden="true"><i class="bi bi-cash-stack"></i></span>
            <h2 class="h5 mb-1"><?= e(__('expenses.list.empty_title')) ?></h2>
            <p class="text-secondary mb-3"><?= e(__('expenses.list.empty_body')) ?></p>
            <a href="/gastos/nuevo" class="btn btn-primary"><?= e(__('nav.fab.gasto')) ?></a>
        </div>
    </div>
<?php else: ?>
    <div class="movement-list">
        <?php foreach ($feed as $item): ?>
            <?php if ($item['tipo'] !== 'gasto'): ?>
                <?php
                $tag = $item['url'] !== null ? 'a' : 'div';
                $href = $item['url'] !== null ? ' href="' . e((string) $item['url']) . '"' : '';
                $amountClass = $item['direccion'] === 'entrada' ? 'text-success' : ($item['direccion'] === 'neutral' ? 'text-secondary' : '');
                ?>
                <<?= $tag ?> class="movement-row"<?= $href ?>>
                    <span class="movement-thumb movement-thumb-empty" aria-hidden="true">
                        <i class="bi <?= e($kindIcons[$item['tipo']] ?? 'bi-dot') ?>"></i>
                    </span>
                    <span class="movement-main">
                        <span class="d-flex align-items-center gap-2">
                            <span class="fw-semibold"><?= e((string) $item['titulo']) ?></span>
                            <span class="badge text-bg-light border"><?= e(__('expenses.list.kinds.' . (string) $item['tipo'])) ?></span>
                        </span>
                        <span class="small text-secondary">
                            <?php if ((string) $item['detalle'] !== ''): ?>
                                <?= e((string) $item['detalle']) ?> ·
                            <?php endif; ?>
                            <?= e(format_date($item['fecha'] ?? null)) ?>
                        </span>
                    </span>
                    <span class="movement-amount <?= e($amountClass) ?>">
                        <?= e($signs[$item['direccion']] ?? '') ?><?= money_html((int) $item['monto_centavos'], (string) $item['moneda']) ?>
                    </span>
                </<?= $tag ?>>
                <?php continue; ?>
            <?php endif; ?>
            <?php
            $row = $item['gasto'];
            $id = (int) $row['id'];
            $state = (string) ($row['estado'] ?? 'asignado');
            $rowTags = $tagMap[$id] ?? [];
            ?>
            <a class="movement-row" href="/gastos/ver?id=<?= $id ?>">
                <?php if (!empty($row['foto_path'])): ?>
                    <img src="/gastos/foto?id=<?= $id ?>" alt="" class="movement-thumb" loading="lazy">
                <?php else: ?>
                    <span class="movement-thumb movement-thumb-empty" aria-hidden="true">
                        <i class="bi bi-receipt"></i>
                    </span>
                <?php endif; ?>

                <span class="movement-main">
                    <span class="d-flex align-items-center gap-2">
                        <span class="fw-semibold"><?= e((string) $row['parte_metodo']) ?></span>
                        <span class="badge <?= e($stateClasses[$state] ?? 'text-bg-secondary') ?>">
                            <?= e(__('expenses.states.' . $state)) ?>
                        </span>
                    </span>
                    <span class="small text-secondary">
                        <?= e((string) ($row['subcategoria_nombre'] ?? __('expenses.show.uncategorized'))) ?>
                        · <?= e(format_date($row['fecha'] ?? null)) ?>
                        <?php if (!empty($row['medio_pago'])): ?>
                            · <?= e(__('expenses.mediums.' . (string) $row['medio_pago'])) ?>
                        <?php endif; ?>
                    </span>
                    <?php if ($rowTags !== []): ?>
                        <span class="d-flex flex-wrap gap-1 mt-1">
                            <?php foreach ($rowTags as $tag): ?>
                                <span class="tag-chip tag-chip-sm">#<?= e((string) $tag['nombre_normalizado']) ?></span>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </span>

                <span class="movement-amount">
                    −<?= money_html((int) $row['monto_centavos'], (string) $row['moneda']) ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
