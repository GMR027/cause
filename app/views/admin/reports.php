<?php
$stats = $stats ?? [];
$users = $stats['users'] ?? [];
$savers = $stats['savers'] ?? [];
$compliance = $stats['compliance'] ?? [];
$methods = $stats['methods'] ?? [];
$debts = $stats['debts'] ?? [];
$emergency = $stats['emergency'] ?? [];
$features = $stats['features'] ?? [];
$evolution = $stats['evolution'] ?? [];
$retention = $stats['retention'] ?? [];
$min = (int) ($stats['min_group'] ?? 10);
$newPerDay = (array) ($users['new_per_day'] ?? []);
$lastNew = $newPerDay !== [] ? $newPerDay[count($newPerDay) - 1] : ['count' => 0];
$pct = static fn (?float $value): string => $value === null
    ? '—'
    : rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . '%';
$evolutionMax = 1;

foreach ($evolution as $point) {
    $evolutionMax = max($evolutionMax, (int) $point['active_users']);
}

$chartWidth = 620;
$chartHeight = 160;
$padding = 24;
$points = [];
$countPoints = count($evolution);

foreach ($evolution as $index => $point) {
    $x = $countPoints <= 1
        ? $padding
        : $padding + ($index * (($chartWidth - 2 * $padding) / max(1, $countPoints - 1)));
    $y = $chartHeight - $padding - (((int) $point['active_users'] / $evolutionMax) * ($chartHeight - 2 * $padding));
    $points[] = round($x, 1) . ',' . round($y, 1);
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('admin.stats.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('admin.stats.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('admin.stats.subtitle', [':min' => (string) $min])) ?></p>
    </div>
    <form method="post" action="/admin/reportes/refrescar" class="m-0">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
            <?= e(__('admin.stats.refresh')) ?>
        </button>
    </form>
</div>

<div class="alert alert-secondary py-2 small" role="status" data-dismissible data-dismiss-key="admin-reports-info">
    <i class="bi bi-incognito me-1" aria-hidden="true"></i>
    <?= e(__('admin.stats.privacy_note')) ?>
    · <?= e(__('admin.stats.generated_at', [':date' => format_datetime($stats['generated_at'] ?? null)])) ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-2">
        <div class="metric-card">
            <span class="metric-label"><?= e(__('admin.stats.users_total')) ?></span>
            <span class="metric-value"><?= (int) ($users['total'] ?? 0) ?></span>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="metric-card">
            <span class="metric-label"><?= e(__('admin.stats.users_active')) ?></span>
            <span class="metric-value"><?= (int) ($users['active'] ?? 0) ?></span>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="metric-card">
            <span class="metric-label"><?= e(__('admin.stats.active_7')) ?></span>
            <span class="metric-value"><?= (int) ($users['active_7'] ?? 0) ?></span>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="metric-card">
            <span class="metric-label"><?= e(__('admin.stats.active_30')) ?></span>
            <span class="metric-value"><?= (int) ($users['active_30'] ?? 0) ?></span>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="metric-card">
            <span class="metric-label"><?= e(__('admin.stats.onboarded')) ?></span>
            <span class="metric-value"><?= (int) ($users['onboarded'] ?? 0) ?></span>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="metric-card">
            <span class="metric-label"><?= e(__('admin.stats.new_today')) ?></span>
            <span class="metric-value"><?= (int) ($lastNew['count'] ?? 0) ?></span>
        </div>
    </div>
</div>

<div class="surface-card mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h2 class="h5 mb-0"><?= e(__('admin.stats.new_per_day_title')) ?></h2>
        <span class="small text-secondary"><?= e(__('admin.stats.new_per_day_hint')) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(__('admin.stats.day')) ?></th>
                    <th class="text-end"><?= e(__('admin.stats.new_users')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($newPerDay, -14) as $day): ?>
                    <tr>
                        <td class="small text-secondary"><?= e((string) $day['date']) ?></td>
                        <td class="text-end"><?= (int) $day['count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['key' => 'savers', 'data' => $savers, 'label' => __('admin.stats.savers'), 'percent' => true],
        ['key' => 'compliance', 'data' => $compliance, 'label' => __('admin.stats.compliance'), 'percent' => true],
        ['key' => 'debts', 'data' => $debts, 'label' => __('admin.stats.debts'), 'percent' => true],
        ['key' => 'emergency', 'data' => $emergency, 'label' => __('admin.stats.emergency'), 'percent' => true],
    ];
    ?>
    <?php foreach ($cards as $card): ?>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="surface-card h-100">
                <span class="small text-secondary d-block mb-1"><?= e($card['label']) ?></span>
                <?php if (!empty($card['data']['enough'])): ?>
                    <p class="display-6 mb-1"><?= e($pct($card['data']['percent'] ?? null)) ?></p>
                    <p class="small text-secondary mb-0">
                        <?php if ($card['key'] === 'debts'): ?>
                            <?= e(__('admin.stats.debt_average', [
                                ':amount' => format_money((int) ($card['data']['average'] ?? 0), 'MXN'),
                            ])) ?>
                        <?php elseif ($card['key'] === 'emergency'): ?>
                            <?= e(__('admin.stats.emergency_months', [
                                ':months' => (string) ($card['data']['average_months'] ?? '—'),
                            ])) ?>
                        <?php else: ?>
                            <?= e(__('admin.stats.of_users', [
                                ':count' => (string) ($card['data']['count'] ?? 0),
                                ':total' => (string) ($card['data']['denominator'] ?? 0),
                            ])) ?>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="h4 mb-1 text-secondary"><?= e(__('admin.stats.insufficient')) ?></p>
                    <p class="small text-secondary mb-0"><?= e(__('admin.stats.insufficient_hint', [':min' => (string) $min])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('admin.stats.methods_title')) ?></h2>
            <?php if (!empty($methods['enough'])): ?>
                <?php foreach ($methods['items'] as $item): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= e((string) $item['name']) ?></span>
                            <span class="text-secondary">
                                <?= (int) $item['count'] ?> · <?= e($pct((float) $item['percent'])) ?>
                            </span>
                        </div>
                        <div class="progress" style="height: .45rem">
                            <div class="progress-bar" style="width: <?= e((string) min(100, (float) $item['percent'])) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="small text-secondary mb-0"><?= e(__('admin.stats.insufficient_hint', [':min' => (string) $min])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('admin.stats.features_title')) ?></h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= e(__('admin.stats.feature')) ?></th>
                            <th class="text-end"><?= e(__('admin.stats.users_short')) ?></th>
                            <th class="text-end"><?= e(__('admin.stats.share')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($features as $key => $feature): ?>
                            <tr>
                                <td><?= e(__('admin.stats.features.' . $key)) ?></td>
                                <td class="text-end"><?= (int) ($feature['users'] ?? 0) ?></td>
                                <td class="text-end">
                                    <?= !empty($feature['enough']) ? e($pct($feature['percent'] ?? null)) : e(__('admin.stats.insufficient')) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="surface-card mb-4">
    <h2 class="h5 mb-3"><?= e(__('admin.stats.evolution_title')) ?></h2>
    <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" class="admin-chart" role="img"
         aria-label="<?= e(__('admin.stats.evolution_title')) ?>">
        <line x1="<?= $padding ?>" y1="<?= $chartHeight - $padding ?>" x2="<?= $chartWidth - $padding ?>"
              y2="<?= $chartHeight - $padding ?>" stroke="currentColor" opacity=".2"></line>
        <polyline points="<?= e(implode(' ', $points)) ?>" fill="none" stroke="currentColor"
                  stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"></polyline>
        <?php foreach ($evolution as $index => $point): ?>
            <?php
            $x = $countPoints <= 1
                ? $padding
                : $padding + ($index * (($chartWidth - 2 * $padding) / max(1, $countPoints - 1)));
            $y = $chartHeight - $padding - (((int) $point['active_users'] / $evolutionMax) * ($chartHeight - 2 * $padding));
            ?>
            <circle cx="<?= round($x, 1) ?>" cy="<?= round($y, 1) ?>" r="3" fill="currentColor"></circle>
            <text x="<?= round($x, 1) ?>" y="<?= $chartHeight - 6 ?>" text-anchor="middle"
                  font-size="9" fill="currentColor" opacity=".65"><?= e((string) $point['month']) ?></text>
        <?php endforeach; ?>
    </svg>
    <div class="table-responsive mt-2">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(__('admin.stats.month')) ?></th>
                    <th class="text-end"><?= e(__('admin.stats.new_users')) ?></th>
                    <th class="text-end"><?= e(__('admin.stats.active_users')) ?></th>
                    <th class="text-end"><?= e(__('admin.stats.savers')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($evolution as $point): ?>
                    <tr>
                        <td><?= e((string) $point['month']) ?></td>
                        <td class="text-end"><?= (int) $point['new_users'] ?></td>
                        <td class="text-end">
                            <?= (int) $point['active_users'] ?>
                            <?php if (empty($point['enough'])): ?>
                                <span class="small text-secondary">(<?= e(__('admin.stats.insufficient')) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= (int) $point['savers'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('admin.stats.retention_title')) ?></h2>
            <?php if (!empty($retention['enough'])): ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= e(__('admin.stats.cohort')) ?></th>
                                <th class="text-end"><?= e(__('admin.stats.cohort_size')) ?></th>
                                <th class="text-end">S0</th>
                                <th class="text-end">S1</th>
                                <th class="text-end">S2</th>
                                <th class="text-end">S3</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($retention['cohorts'] as $cohort): ?>
                                <tr>
                                    <td><?= e((string) $cohort['label']) ?></td>
                                    <td class="text-end"><?= (int) $cohort['size'] ?></td>
                                    <?php for ($week = 0; $week <= 3; $week++): ?>
                                        <td class="text-end">
                                            <?= isset($cohort['weeks'][$week]) ? e($pct((float) $cohort['weeks'][$week])) : '—' ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="small text-secondary mb-0"><?= e(__('admin.stats.insufficient_hint', [':min' => (string) $min])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('admin.stats.score_title')) ?></h2>
            <p class="mb-1"><span class="badge text-bg-secondary"><?= e(__('score.private')) ?></span></p>
            <p class="small text-secondary mb-0"><?= e(__('admin.stats.score_hint')) ?></p>
        </div>
    </div>
</div>
