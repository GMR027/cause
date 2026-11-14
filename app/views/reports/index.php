<?php
$netWorth = $netWorth ?? [];
$history = $history ?? [];
$subscriptions = $subscriptions ?? [];
$score = $score ?? [];
$currency = (string) ($netWorth['currency'] ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$cash = (int) ($netWorth['cash_centavos'] ?? 0);
$dataPoints = array_values(array_filter($history, static fn (array $point): bool => !empty($point['has_data'])));
$chartWidth = 720;
$chartHeight = 220;
$padding = 34;
$values = array_map(static fn (array $point): int => (int) $point['neto_centavos'], $dataPoints);
$min = $values !== [] ? min($values) : 0;
$max = $values !== [] ? max($values) : 0;

if ($min === $max) {
    $min -= 100;
    $max += 100;
}

$range = max(1, $max - $min);
$points = [];
$count = count($history);
$indexByMonth = [];

foreach ($history as $index => $point) {
    $indexByMonth[(string) $point['month']] = $index;
}

foreach ($dataPoints as $point) {
    $index = $indexByMonth[(string) $point['month']] ?? 0;
    $x = $padding + ($count <= 1 ? 0 : $index * (($chartWidth - 2 * $padding) / max(1, $count - 1)));
    $y = $chartHeight - $padding - ((((int) $point['neto_centavos'] - $min) / $range) * ($chartHeight - 2 * $padding));
    $points[] = round($x, 1) . ',' . round($y, 1);
}

$zeroY = null;

if ($min < 0 && $max > 0) {
    $zeroY = $chartHeight - $padding - (((0 - $min) / $range) * ($chartHeight - 2 * $padding));
}
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

<div class="surface-card mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h2 class="h5 mb-0"><?= e(__('reports.chart.title')) ?></h2>
        <span class="small text-secondary"><?= e(__('reports.chart.month')) ?></span>
    </div>

    <?php if ($dataPoints === []): ?>
        <p class="small text-secondary mb-0"><?= e(__('reports.chart.empty')) ?></p>
    <?php else: ?>
        <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" class="report-chart" role="img"
             aria-label="<?= e(__('reports.chart.title')) ?>">
            <line x1="<?= $padding ?>" y1="<?= $chartHeight - $padding ?>" x2="<?= $chartWidth - $padding ?>"
                  y2="<?= $chartHeight - $padding ?>" stroke="currentColor" opacity=".15"></line>
            <?php if ($zeroY !== null): ?>
                <line x1="<?= $padding ?>" y1="<?= round($zeroY, 1) ?>" x2="<?= $chartWidth - $padding ?>"
                      y2="<?= round($zeroY, 1) ?>" stroke="currentColor" opacity=".25" stroke-dasharray="4 4"></line>
            <?php endif; ?>
            <polyline points="<?= e(implode(' ', $points)) ?>" fill="none" stroke="currentColor"
                      stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"></polyline>
            <?php foreach ($dataPoints as $point): ?>
                <?php
                $index = $indexByMonth[(string) $point['month']] ?? 0;
                $x = $padding + ($count <= 1 ? 0 : $index * (($chartWidth - 2 * $padding) / max(1, $count - 1)));
                $y = $chartHeight - $padding - ((((int) $point['neto_centavos'] - $min) / $range) * ($chartHeight - 2 * $padding));
                ?>
                <circle cx="<?= round($x, 1) ?>" cy="<?= round($y, 1) ?>" r="3.5" fill="currentColor"></circle>
            <?php endforeach; ?>
            <?php foreach ($history as $index => $point): ?>
                <?php if ($index % 2 === 0 || $index === $count - 1): ?>
                    <?php $x = $padding + ($count <= 1 ? 0 : $index * (($chartWidth - 2 * $padding) / max(1, $count - 1))); ?>
                    <text x="<?= round($x, 1) ?>" y="<?= $chartHeight - 8 ?>" text-anchor="middle"
                          font-size="9" fill="currentColor" opacity=".65"><?= e((string) $point['month']) ?></text>
                <?php endif; ?>
            <?php endforeach; ?>
        </svg>
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
