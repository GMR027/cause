<?php
$score = $score ?? [];
$hasData = !empty($score['has_data']);
$trend = $score['trend'] ?? null;
$history = $score['history'] ?? [];
$components = $score['components'] ?? [];
$dataPoints = array_values(array_filter($history, static fn (array $point): bool => !empty($point['has_data'])));
$chartWidth = 720;
$chartHeight = 200;
$padding = 30;
$points = [];
$count = count($history);
$indexByMonth = [];

foreach ($history as $index => $point) {
    $indexByMonth[(string) $point['month']] = $index;
}

foreach ($dataPoints as $point) {
    $index = $indexByMonth[(string) $point['month']] ?? 0;
    $x = $padding + ($count <= 1 ? 0 : $index * (($chartWidth - 2 * $padding) / max(1, $count - 1)));
    $y = $chartHeight - $padding - (((int) $point['score'] / 100) * ($chartHeight - 2 * $padding));
    $points[] = round($x, 1) . ',' . round($y, 1);
}

$number = static function (float $value): string {
    return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
};
?>
<div class="surface-card mb-4" id="score">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <span class="small text-secondary d-block"><?= e(__('score.detail_title')) ?></span>
            <h2 class="h3 mb-0">
                <?php if ($hasData): ?>
                    <?= e(__('score.summary', [':score' => (string) (int) $score['score']])) ?>
                <?php else: ?>
                    <?= e(__('score.title')) ?>
                <?php endif; ?>
            </h2>
        </div>
        <?php if ($hasData && $trend !== null): ?>
            <span class="badge text-bg-light border"><?= e(__('score.trend.' . $trend)) ?></span>
        <?php endif; ?>
    </div>

    <?php if (!$hasData): ?>
        <p class="mb-1"><?= e(__('score.no_data')) ?></p>
        <p class="small text-secondary mb-0"><?= e(($score['reason'] ?? null) === 'sin_metodo' ? __('score.reasons.sin_metodo') : __('score.no_data_hint')) ?></p>
    <?php else: ?>
        <?php if (!empty($score['preliminary'])): ?>
            <p class="small mb-3">
                <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i><?= e(__('score.preliminary')) ?>
            </p>
        <?php endif; ?>
        <div class="row g-3 mb-4">
            <?php foreach ($components as $component): ?>
                <div class="col-12 col-md-6">
                    <div class="score-component">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                            <span class="fw-semibold"><?= e((string) $component['label']) ?></span>
                            <span class="small text-secondary">
                                <?= e(__('score.weight', [':weight' => (string) (int) $component['weight']])) ?>
                            </span>
                        </div>
                        <div class="finance-progress mb-1" aria-hidden="true">
                            <span style="width: <?= (int) round((float) $component['score']) ?>%"></span>
                        </div>
                        <div class="d-flex justify-content-between small text-secondary">
                            <span><?= (int) round((float) $component['score']) ?>/100</span>
                            <span>
                                <?= e(__('score.points', [
                                    ':points' => $number((float) $component['points']),
                                    ':weight' => (string) (int) $component['weight'],
                                ])) ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h3 class="h6 mb-0"><?= e(__('score.evolution_title')) ?></h3>
            <span class="small text-secondary"><?= e(__('score.month')) ?></span>
        </div>

        <?php if ($dataPoints === []): ?>
            <p class="small text-secondary mb-0"><?= e(__('score.evolution_empty')) ?></p>
        <?php else: ?>
            <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" class="report-chart mb-2" role="img"
                 aria-label="<?= e(__('score.evolution_title')) ?>">
                <?php foreach ([0, 25, 50, 75, 100] as $guide): ?>
                    <?php $y = $chartHeight - $padding - (($guide / 100) * ($chartHeight - 2 * $padding)); ?>
                    <line x1="<?= $padding ?>" y1="<?= round($y, 1) ?>" x2="<?= $chartWidth - $padding ?>"
                          y2="<?= round($y, 1) ?>" stroke="currentColor"
                          opacity="<?= $guide === 0 ? '.2' : '.08' ?>"></line>
                <?php endforeach; ?>
                <polyline points="<?= e(implode(' ', $points)) ?>" fill="none" stroke="currentColor"
                          stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"></polyline>
                <?php foreach ($dataPoints as $point): ?>
                    <?php
                    $index = $indexByMonth[(string) $point['month']] ?? 0;
                    $x = $padding + ($count <= 1 ? 0 : $index * (($chartWidth - 2 * $padding) / max(1, $count - 1)));
                    $y = $chartHeight - $padding - (((int) $point['score'] / 100) * ($chartHeight - 2 * $padding));
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
    <?php endif; ?>

    <p class="small text-secondary mb-0 mt-2">
        <?= e(__('score.disclaimer')) ?> · <?= e(__('score.private')) ?>
    </p>
</div>
