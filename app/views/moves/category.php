<?php

$currency = (string) ($currency ?? 'MXN');
$category = $category ?? [];
$moves = $category['moves'] ?? [];
$period = $period ?? null;
$name = (string) ($category['nombre'] ?? '');
$projection = $projection ?? null;
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('moves.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e($name) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('moves.category.summary')) ?></p>
        <?php if ($period !== null): ?>
            <p class="small text-secondary mb-0 mt-1">
                <?= e(format_date($period['inicio'] ?? null)) ?> – <?= e(format_date($period['fin'] ?? null)) ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/mover" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= e(__('moves.category.back')) ?>
        </a>
        <a href="/movimientos?parte=<?= rawurlencode($name) ?>" class="btn btn-primary">
            <?= e(__('nav.movimientos')) ?>
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('moves.category.original')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($category['original'] ?? 0), $currency) ?></span>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('moves.category.adjusted')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($category['ajustado'] ?? 0), $currency) ?></span>
            <span class="small text-secondary">
                <?= e(__('moves.category.in')) ?>: +<?= money_html((int) ($category['entradas'] ?? 0), $currency) ?>
                · <?= e(__('moves.category.out')) ?>: −<?= money_html((int) ($category['salidas'] ?? 0), $currency) ?>
            </span>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('moves.category.spent')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($category['gastado'] ?? 0), $currency) ?></span>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="finance-card finance-card-accent">
            <span class="finance-card-label"><?= e(__('moves.category.available')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($category['disponible'] ?? 0), $currency) ?></span>
        </div>
    </div>
</div>

<?php if (is_array($projection)): ?>
    <div class="surface-card mb-4">
        <h2 class="h5 mb-2"><?= e(__('projection.title')) ?></h2>
        <?php if ((int) ($projection['gastado_centavos'] ?? 0) <= 0): ?>
            <p class="small text-secondary mb-0"><?= e(__('projection.no_data')) ?></p>
        <?php elseif (!empty($projection['en_riesgo'])): ?>
            <p class="mb-1">
                <?php if (!empty($projection['agotada'])): ?>
                    <?= e(__('projection.category_depleted', [':category' => $name])) ?>
                <?php else: ?>
                    <?= e(__('projection.category_risk', [
                        ':category' => $name,
                        ':date' => !empty($projection['agotamiento_fecha'])
                            ? format_date($projection['agotamiento_fecha'] . ' 12:00:00')
                            : '—',
                    ])) ?>
                <?php endif; ?>
            </p>
            <p class="small text-secondary mb-0">
                <?= e(__('projection.projected_spend', [
                    ':amount' => format_money((int) $projection['proyectado_centavos'], $currency),
                ])) ?>
            </p>
        <?php else: ?>
            <p class="mb-1">
                <?= e(__('projection.category_safe', [
                    ':amount' => format_money((int) $projection['proyectado_disponible_centavos'], $currency),
                ])) ?>
            </p>
            <p class="small text-secondary mb-0">
                <?= e(__('projection.projected_spend', [
                    ':amount' => format_money((int) $projection['proyectado_centavos'], $currency),
                ])) ?>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="surface-card">
    <h2 class="h5 mb-3"><?= e(__('moves.history')) ?></h2>

    <?php if ($moves === []): ?>
        <p class="text-secondary small mb-0"><?= e(__('moves.history_empty')) ?></p>
    <?php else: ?>
        <div class="finance-list">
            <?php foreach ($moves as $move): ?>
                <?php
                $inbound = (string) $move['destino'] === $name;
                $other = $inbound ? (string) $move['origen'] : (string) $move['destino'];
                ?>
                <div class="finance-row">
                    <div class="flex-grow-1">
                        <span class="fw-semibold d-block">
                            <i class="bi <?= $inbound ? 'bi-arrow-down-left-circle text-success' : 'bi-arrow-up-right-circle text-danger' ?> me-1"
                               aria-hidden="true"></i>
                            <?= $inbound ? __('moves.category.in') : __('moves.category.out') ?>
                            · <?= e($other) ?>
                        </span>
                        <span class="small text-secondary">
                            <?= e(__('moves.reasons.' . (string) $move['motivo_catalogo'])) ?>
                            · <?= e(format_datetime($move['fecha'] ?? null)) ?>
                            <?php if (!empty($move['motivo_texto'])): ?>
                                · <?= e((string) $move['motivo_texto']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <span class="fw-semibold <?= $inbound ? 'text-success' : 'text-danger' ?>">
                        <?= $inbound ? '+' : '−' ?><?= money_html((int) $move['monto_centavos'], (string) $move['moneda']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
