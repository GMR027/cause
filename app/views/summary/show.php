<?php

$content = $content ?? [];
$summary = $summary ?? [];
$currency = (string) ($content['moneda'] ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$categories = $content['categories'] ?? [];
$moves = $content['moves'] ?? [];
$withdrawals = $content['withdrawals'] ?? [];
$contributions = $content['contributions'] ?? [];
$percent = (float) ($content['percent_spent'] ?? 0);
$inicio = (string) ($summary['periodo_inicio'] ?? '');
$fin = (string) ($summary['periodo_fin'] ?? '');
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('summary.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(format_date($inicio)) ?> – <?= e(format_date($fin)) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('summary.period', [
            ':range' => format_date($inicio) . ' – ' . format_date($fin),
        ])) ?></p>
        <p class="small text-secondary mb-0 mt-1">
            <?= e(__('summary.generated', [':date' => format_date($summary['generado_at'] ?? null)])) ?>
        </p>
    </div>
    <a href="/resumen" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= e(__('summary.back')) ?>
    </a>
</div>

<div class="alert alert-info py-2 small" data-dismissible data-dismiss-key="summary-info">
    <i class="bi bi-lock me-1" aria-hidden="true"></i><?= e(__('summary.immutable')) ?>
</div>

<div class="row g-3 mb-4">
    <?php
    $metrics = [
        ['key' => 'income_centavos', 'label' => 'income'],
        ['key' => 'expense_centavos', 'label' => 'expense'],
        ['key' => 'savings_centavos', 'label' => 'savings'],
        ['key' => 'investment_centavos', 'label' => 'investment'],
        ['key' => 'donation_centavos', 'label' => 'donation'],
        ['key' => 'no_controlado_centavos', 'label' => 'uncontrolled'],
    ];
    ?>
    <?php foreach ($metrics as $metric): ?>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="finance-card">
                <span class="finance-card-label"><?= e(__('summary.metrics.' . $metric['label'])) ?></span>
                <span class="finance-card-value">
                    <?= money_html((int) ($content[$metric['key']] ?? 0), $currency) ?>
                </span>
                <?php if ($metric['label'] === 'expense'): ?>
                    <span class="small text-secondary">
                        <?= e(__('summary.metrics.percent_spent', [':percent' => (string) $percent])) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-7">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('summary.categories.title')) ?></h2>

            <?php if ($categories === []): ?>
                <p class="small text-secondary mb-0"><?= e(__('summary.categories.empty')) ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?= e(__('moves.columns.category')) ?></th>
                                <th scope="col" class="text-end"><?= e(__('summary.categories.assigned')) ?></th>
                                <th scope="col" class="text-end"><?= e(__('summary.categories.spent')) ?></th>
                                <th scope="col" class="text-end"><?= e(__('summary.categories.excess')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?= e((string) ($category['nombre'] ?? '')) ?></td>
                                    <td class="text-end"><?= money_html((int) ($category['asignado_centavos'] ?? 0), $currency) ?></td>
                                    <td class="text-end"><?= money_html((int) ($category['gastado_centavos'] ?? 0), $currency) ?></td>
                                    <td class="text-end">
                                        <?php if ((int) ($category['exceso_centavos'] ?? 0) > 0): ?>
                                            <span class="text-danger">
                                                <?= money_html((int) $category['exceso_centavos'], $currency) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-secondary">—</span>
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

    <div class="col-12 col-xl-5">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('summary.moves.title')) ?></h2>

            <?php if ($moves === []): ?>
                <p class="small text-secondary mb-0"><?= e(__('summary.moves.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($moves as $move): ?>
                        <div class="finance-row">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block">
                                    <?= e((string) ($move['origen'] ?? '')) ?> → <?= e((string) ($move['destino'] ?? '')) ?>
                                </span>
                                <span class="small text-secondary">
                                    <?= e(__('moves.reasons.' . (string) ($move['motivo'] ?? 'otro'))) ?>
                                    · <?= e(format_date($move['fecha'] ?? null)) ?>
                                </span>
                            </div>
                            <span class="fw-semibold"><?= money_html((int) ($move['monto_centavos'] ?? 0), $currency) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('summary.withdrawals.title')) ?></h2>

            <?php if ($withdrawals === []): ?>
                <p class="small text-secondary mb-0"><?= e(__('summary.withdrawals.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($withdrawals as $withdrawal): ?>
                        <div class="finance-row">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block">
                                    <?= money_html((int) ($withdrawal['monto_centavos'] ?? 0), $currency) ?>
                                </span>
                                <span class="small text-secondary">
                                    <?= e(__('summary.withdrawals.' . (string) ($withdrawal['tipo'] ?? 'ahorro'))) ?>
                                    · <?= e(format_date($withdrawal['fecha'] ?? null)) ?>
                                    <?php if (!empty($withdrawal['texto'])): ?>
                                        · <?= e((string) $withdrawal['texto']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('summary.contributions.title')) ?></h2>

            <?php if ($contributions === []): ?>
                <p class="small text-secondary mb-0"><?= e(__('summary.contributions.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($contributions as $contribution): ?>
                        <div class="finance-row">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block">
                                    <?= money_html((int) ($contribution['monto_centavos'] ?? 0), $currency) ?>
                                </span>
                                <span class="small text-secondary">
                                    <?= e(__('summary.withdrawals.' . (string) ($contribution['tipo'] ?? 'ahorro'))) ?>
                                    · <?= e(format_date($contribution['fecha'] ?? null)) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="surface-card">
    <h2 class="h5 mb-1"><?= e(__('summary.reflection.title')) ?></h2>
    <p class="small text-secondary"><?= e(__('summary.reflection.hint')) ?></p>

    <form method="post" action="/resumen/reflexion">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) ($summary['id'] ?? 0) ?>">
        <div class="mb-3">
            <textarea class="form-control" name="reflexion" rows="5" maxlength="4000"
                      placeholder="<?= e(__('summary.reflection.placeholder')) ?>"><?= e((string) ($summary['reflexion'] ?? '')) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">
            <?= e(__('summary.reflection.save')) ?>
        </button>
    </form>
</div>
