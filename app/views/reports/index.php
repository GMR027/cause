<?php
$netWorth = $netWorth ?? [];
$history = $history ?? [];
$subscriptions = $subscriptions ?? [];
$frequent = $frequent ?? ['days' => 90, 'items' => [], 'max' => 0];
$score = $score ?? [];
$currency = (string) ($netWorth['currency'] ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$cash = (int) ($netWorth['cash_centavos'] ?? 0);
$series = $series ?? [];
$range = (string) ($range ?? '6m');
$ranges = $ranges ?? ['1m', '3m', '6m', '1a', 'todo'];

// Gráfica estilo bolsa: área con degradado, línea base en el valor inicial,
// eje Y a la derecha y tooltip con cruz (assets/js/89-reports.js).
$chartWidth = 720;
$chartHeight = 260;
$padLeft = 8;
$padRight = 78;
$padTop = 16;
$padBottom = 30;
$plotW = $chartWidth - $padLeft - $padRight;
$plotH = $chartHeight - $padTop - $padBottom;
$values = array_map(static fn (array $point): int => (int) $point['neto_centavos'], $series);
$first = $values !== [] ? $values[0] : 0;
$last = $values !== [] ? $values[count($values) - 1] : 0;
$change = $last - $first;
$changePercent = $first !== 0 ? ($change / abs($first)) * 100 : null;
$trend = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat');
$min = $values !== [] ? min($values) : 0;
$max = $values !== [] ? max($values) : 0;

if ($min === $max) {
    $min -= 100;
    $max += 100;
}

// Un poco de aire arriba y abajo para que la línea no toque los bordes.
$margin = (int) round(($max - $min) * 0.08);
$min -= $margin;
$max += $margin;
$rangeValue = max(1, $max - $min);
$count = count($series);
$xFor = static fn (int $index): float => $padLeft + ($count <= 1 ? $plotW : ($index / ($count - 1)) * $plotW);
$yFor = static fn (int $value): float => $padTop + (1 - (($value - $min) / $rangeValue)) * $plotH;
$linePoints = [];
$chartData = [];

foreach ($series as $index => $point) {
    $x = round($xFor($index), 1);
    $y = round($yFor((int) $point['neto_centavos']), 1);
    $linePoints[] = $x . ',' . $y;
    $chartData[] = [
        'x' => $x,
        'y' => $y,
        'date' => format_date($point['fecha'] . ' 12:00:00'),
        'value' => format_money((int) $point['neto_centavos'], $currency),
        'change' => format_money((int) $point['neto_centavos'] - $first, $currency),
        'up' => (int) $point['neto_centavos'] >= $first,
    ];
}

if ($count === 1) {
    array_unshift($linePoints, $padLeft . ',' . explode(',', $linePoints[0])[1]);
}

$baseY = round($yFor($first), 1);
$bottomY = $padTop + $plotH;
$areaPath = $linePoints !== []
    ? 'M' . explode(',', $linePoints[0])[0] . ',' . $bottomY . ' L' . implode(' L', $linePoints)
        . ' L' . explode(',', $linePoints[count($linePoints) - 1])[0] . ',' . $bottomY . ' Z'
    : '';
$yTicks = [$max, (int) round(($max + $min) / 2), $min];
$xTicks = $count <= 1 ? [0] : array_values(array_unique([0, (int) round(($count - 1) / 3), (int) round(2 * ($count - 1) / 3), $count - 1]));
$lastPoint = $linePoints !== [] ? explode(',', $linePoints[count($linePoints) - 1]) : null;
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('nav.reportes')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('reports.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('reports.subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?= partial('partials/lesson', ['lesson' => lesson_for('patrimonio')]) ?>
        <a href="/reportes/suscripciones" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
            <?= e(__('reports.subscriptions.title')) ?>
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-7">
        <div class="surface-card h-100">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <span class="small text-secondary d-block"><?= e(__('reports.net_worth.title')) ?></span>
                    <h2 class="h3 mb-0"><?= money_html((int) ($netWorth['net_centavos'] ?? 0), $currency) ?></h2>
                </div>
                <span class="badge text-bg-light border text-wrap text-start"><?= e(__('reports.net_worth.hint')) ?></span>
            </div>

            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <div class="report-metric">
                        <span class="small text-secondary d-block"><?= e(__('reports.net_worth.assets')) ?></span>
                        <span class="fw-semibold"><?= money_html((int) ($netWorth['assets_centavos'] ?? 0), $currency) ?></span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="report-metric">
                        <span class="small text-secondary d-block"><?= e(__('reports.net_worth.liabilities')) ?></span>
                        <span class="fw-semibold"><?= money_html((int) ($netWorth['liabilities_centavos'] ?? 0), $currency) ?></span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="report-metric">
                        <span class="small text-secondary d-block"><?= e(__('reports.net_worth.savings')) ?></span>
                        <span class="fw-semibold"><?= money_html((int) ($netWorth['savings_centavos'] ?? 0), $currency) ?></span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="report-metric">
                        <span class="small text-secondary d-block"><?= e(__('reports.net_worth.investment')) ?></span>
                        <span class="fw-semibold"><?= money_html((int) ($netWorth['investment_centavos'] ?? 0), $currency) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="surface-card h-100">
            <h2 class="h5 mb-1"><?= e(__('reports.cash.title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('reports.cash.hint')) ?></p>
            <form method="post" action="/reportes/efectivo" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-12">
                    <label class="form-label small" for="networth-cash"><?= e(__('reports.cash.label')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" inputmode="decimal" class="form-control" id="networth-cash" name="efectivo"
                               value="<?= $cash > 0 ? e(number_format($cash / 100, 2, '.', '')) : '' ?>"
                               placeholder="0.00">
                        <span class="input-group-text"><?= e($currency) ?></span>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-outline-primary"><?= e(__('reports.cash.save')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= partial('partials/score-detail', ['score' => $score]) ?>

<div class="surface-card mb-4 stock-card stock-<?= e($trend) ?>">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-2">
        <div>
            <h2 class="h6 text-secondary mb-1"><?= e(__('reports.chart.title')) ?></h2>
            <?php if ($series !== []): ?>
                <div class="stock-value" data-stock-value><?= money_html($last, $currency) ?></div>
                <div class="stock-change" data-stock-change>
                    <i class="bi <?= $trend === 'down' ? 'bi-caret-down-fill' : ($trend === 'up' ? 'bi-caret-up-fill' : 'bi-dash') ?>" aria-hidden="true"></i>
                    <?= e(($change > 0 ? '+' : '') . format_money($change, $currency)) ?>
                    <?php if ($changePercent !== null): ?>
                        (<?= e(($change > 0 ? '+' : '') . number_format($changePercent, 2)) ?> %)
                    <?php endif; ?>
                    <span class="text-secondary fw-normal">· <?= e(__('reports.chart.ranges_label.' . $range)) ?></span>
                </div>
            <?php endif; ?>
        </div>
        <nav class="stock-ranges" aria-label="<?= e(__('reports.chart.range')) ?>">
            <?php foreach ($ranges as $option): ?>
                <a href="/reportes?rango=<?= e((string) $option) ?>"
                   class="stock-range <?= $option === $range ? 'active' : '' ?>"
                   <?= $option === $range ? 'aria-current="true"' : '' ?>>
                    <?= e(__('reports.chart.ranges.' . (string) $option)) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <?php if ($series === []): ?>
        <p class="small text-secondary mb-0"><?= e(__('reports.chart.empty')) ?></p>
    <?php else: ?>
        <div class="stock-chart-wrap" data-stock-chart
             data-points="<?= e((string) json_encode($chartData, JSON_UNESCAPED_UNICODE)) ?>"
             data-width="<?= $chartWidth ?>" data-height="<?= $chartHeight ?>"
             data-top="<?= $padTop ?>" data-bottom="<?= $bottomY ?>">
            <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" class="report-chart stock-chart" role="img"
                 aria-label="<?= e(__('reports.chart.aria', [
                     ':first' => format_money($first, $currency),
                     ':last' => format_money($last, $currency),
                 ])) ?>">
                <defs>
                    <linearGradient id="stockFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="currentColor" stop-opacity=".28"></stop>
                        <stop offset="100%" stop-color="currentColor" stop-opacity="0"></stop>
                    </linearGradient>
                </defs>
                <?php foreach ($yTicks as $tick): ?>
                    <?php $ty = round($yFor($tick), 1); ?>
                    <line x1="<?= $padLeft ?>" y1="<?= $ty ?>" x2="<?= $padLeft + $plotW ?>" y2="<?= $ty ?>"
                          class="stock-grid"></line>
                    <text x="<?= $chartWidth - 4 ?>" y="<?= $ty + 4 ?>" text-anchor="end" class="stock-axis">
                        <?= e(format_money($tick, $currency)) ?>
                    </text>
                <?php endforeach; ?>
                <line x1="<?= $padLeft ?>" y1="<?= $baseY ?>" x2="<?= $padLeft + $plotW ?>" y2="<?= $baseY ?>"
                      class="stock-baseline"></line>
                <path d="<?= e($areaPath) ?>" class="stock-area" fill="url(#stockFill)"></path>
                <polyline points="<?= e(implode(' ', $linePoints)) ?>" class="stock-line" fill="none"></polyline>
                <?php foreach ($xTicks as $tickIndex): ?>
                    <text x="<?= round(min($padLeft + $plotW - 20, max($padLeft + 20, $xFor($tickIndex))), 1) ?>"
                          y="<?= $chartHeight - 8 ?>" text-anchor="middle" class="stock-axis">
                        <?= e(format_date(($series[$tickIndex]['fecha'] ?? '') . ' 12:00:00')) ?>
                    </text>
                <?php endforeach; ?>
                <?php if ($lastPoint !== null): ?>
                    <circle cx="<?= e($lastPoint[0]) ?>" cy="<?= e($lastPoint[1]) ?>" r="9" class="stock-pulse"></circle>
                    <circle cx="<?= e($lastPoint[0]) ?>" cy="<?= e($lastPoint[1]) ?>" r="4" class="stock-dot"></circle>
                <?php endif; ?>
                <g class="stock-cursor" data-stock-cursor hidden>
                    <line y1="<?= $padTop ?>" y2="<?= $bottomY ?>" class="stock-cursor-line"></line>
                    <circle r="5" class="stock-cursor-dot"></circle>
                </g>
            </svg>
            <div class="stock-tooltip" data-stock-tooltip hidden>
                <span class="stock-tooltip-value" data-tooltip-value></span>
                <span class="stock-tooltip-change" data-tooltip-change></span>
                <span class="stock-tooltip-date" data-tooltip-date></span>
            </div>
        </div>
        <?php if ($count < 2): ?>
            <p class="small text-secondary mt-2 mb-0"><?= e(__('reports.chart.few_points')) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="surface-card">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h5 mb-0"><?= e(__('reports.subscriptions.title')) ?></h2>
            <p class="small text-secondary mb-0"><?= e(__('reports.subscriptions.hint')) ?></p>
        </div>
        <a href="/reportes/suscripciones" class="btn btn-sm btn-outline-secondary">
            <?= e(__('reports.subscriptions.view')) ?>
        </a>
    </div>

    <?php if (($subscriptions['count'] ?? 0) === 0): ?>
        <p class="small text-secondary mb-0"><?= e(__('reports.subscriptions.empty')) ?></p>
    <?php else: ?>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="report-metric">
                    <span class="small text-secondary d-block"><?= e(__('reports.subscriptions.monthly')) ?></span>
                    <span class="fw-semibold"><?= money_html((int) $subscriptions['monthly_total_centavos'], $currency) ?></span>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="report-metric">
                    <span class="small text-secondary d-block"><?= e(__('reports.subscriptions.annual')) ?></span>
                    <span class="fw-semibold"><?= money_html((int) $subscriptions['annual_total_centavos'], $currency) ?></span>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="report-metric">
                    <span class="small text-secondary d-block"><?= e(__('reports.subscriptions.count', [':count' => (string) $subscriptions['count']])) ?></span>
                    <span class="small text-secondary"><?= e(__('reports.subscriptions.annual_message', [
                        ':amount' => format_money((int) $subscriptions['annual_total_centavos'], $currency),
                    ])) ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="surface-card mt-4">
    <div class="mb-3">
        <h2 class="h5 mb-0"><?= e(__('reports.frequent.title')) ?></h2>
        <p class="small text-secondary mb-0"><?= e(__('reports.frequent.hint', [':days' => (string) $frequent['days']])) ?></p>
    </div>

    <?php if ($frequent['items'] === []): ?>
        <p class="small text-secondary mb-0"><?= e(__('reports.frequent.empty')) ?></p>
    <?php else: ?>
        <ol class="frequent-list list-unstyled mb-0">
            <?php foreach ($frequent['items'] as $i => $item): ?>
                <?php $width = $frequent['max'] > 0 ? max(4, (int) round($item['veces'] * 100 / $frequent['max'])) : 0; ?>
                <li class="frequent-item">
                    <span class="frequent-rank"><?= $i + 1 ?></span>
                    <div class="frequent-body">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div class="text-truncate">
                                <span class="fw-semibold"><?= e($item['label']) ?></span>
                                <?php if ($item['part'] !== ''): ?>
                                    <span class="small text-secondary">· <?= e($item['part']) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="small fw-semibold text-nowrap">
                                <?= e($item['veces'] === 1 ? __('reports.frequent.times_one') : __('reports.frequent.times', [':count' => (string) $item['veces']])) ?>
                            </span>
                        </div>
                        <div class="frequent-bar" role="img" aria-label="<?= e(__('reports.frequent.aria')) ?>: <?= $width ?>%">
                            <span style="width: <?= $width ?>%"></span>
                        </div>
                        <div class="small text-secondary d-flex flex-wrap gap-3">
                            <span><?= e(__('reports.frequent.total')) ?>: <?= money_html($item['total'], $currency) ?></span>
                            <span><?= e(__('reports.frequent.average')) ?>: <?= money_html($item['promedio'], $currency) ?></span>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
