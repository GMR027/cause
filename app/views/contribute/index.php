<?php

$groups = [
    [
        'title' => __('savings.title'),
        'icon' => 'bi-piggy-bank',
        'actions' => [
            ['label' => __('savings.actions.contribute'), 'icon' => 'bi-plus-circle', 'href' => '/ahorro#savingsContribute'],
            ['label' => __('savings.actions.withdraw'), 'icon' => 'bi-dash-circle', 'href' => '/ahorro#savingsWithdraw'],
        ],
    ],
    [
        'title' => __('investment.title'),
        'icon' => 'bi-graph-up-arrow',
        'actions' => [
            ['label' => __('investment.actions.contribute'), 'icon' => 'bi-plus-circle', 'href' => '/inversion#investmentContribute'],
            ['label' => __('investment.actions.withdraw'), 'icon' => 'bi-dash-circle', 'href' => '/inversion#investmentWithdraw'],
        ],
    ],
    [
        'title' => __('donation.title'),
        'icon' => 'bi-heart',
        'actions' => [
            ['label' => __('donation.actions.donate'), 'icon' => 'bi-heart-fill', 'href' => '/donacion#donationDonate'],
        ],
    ],
];
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('nav.fab.aportar')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('nav.contribute_hint')) ?></p>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($groups as $group): ?>
        <div class="col-12 col-lg-4">
            <div class="surface-card h-100">
                <h2 class="h6 mb-3">
                    <i class="bi <?= e($group['icon']) ?> me-1" aria-hidden="true"></i>
                    <?= e($group['title']) ?>
                </h2>
                <div class="d-grid gap-2">
                    <?php foreach ($group['actions'] as $action): ?>
                        <a class="app-sheet-action" href="<?= e($action['href']) ?>">
                            <span class="app-sheet-icon">
                                <i class="bi <?= e($action['icon']) ?>" aria-hidden="true"></i>
                            </span>
                            <span class="app-sheet-text">
                                <span class="fw-semibold"><?= e($action['label']) ?></span>
                            </span>
                            <i class="bi bi-chevron-right ms-auto" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
