<?php
$subscriptions = $subscriptions ?? [];
$currency = (string) ($currency ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$annualTotal = (int) ($annual_total_centavos ?? 0);
$monthlyTotal = (int) ($monthly_total_centavos ?? 0);
$count = (int) ($count ?? 0);
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('reports.subscriptions.title')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('reports.subscriptions.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('reports.subscriptions.subtitle')) ?></p>
    </div>
    <a href="/reportes" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= e(__('nav.reportes')) ?>
    </a>
</div>

<div class="alert alert-secondary py-2 small" role="status">
    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
    <?= e(__('reports.subscriptions.hint')) ?>
</div>

<?php if ($subscriptions === []): ?>
    <div class="surface-card text-center py-5">
        <i class="bi bi-arrow-repeat fs-2 text-secondary" aria-hidden="true"></i>
        <p class="mt-2 mb-1 fw-semibold"><?= e(__('reports.subscriptions.empty')) ?></p>
        <p class="small text-secondary mb-0"><?= e(__('reports.subscriptions.empty_hint')) ?></p>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="finance-card">
                <span class="finance-card-label"><?= e(__('reports.subscriptions.count', [':count' => (string) $count])) ?></span>
                <span class="finance-card-value"><?= $count ?></span>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="finance-card">
                <span class="finance-card-label"><?= e(__('reports.subscriptions.monthly')) ?></span>
                <span class="finance-card-value"><?= money_html($monthlyTotal, $currency) ?></span>
            </div>
        </div>
        <div class="col-12 col-xl-3">
            <div class="finance-card finance-card-accent">
                <span class="finance-card-label"><?= e(__('reports.subscriptions.annual')) ?></span>
                <span class="finance-card-value"><?= money_html($annualTotal, $currency) ?></span>
            </div>
        </div>
        <div class="col-12 col-xl-3">
            <div class="finance-card">
                <span class="finance-card-label"><?= e(__('reports.subscriptions.savings_title')) ?></span>
                <span class="finance-card-value" data-subscription-total>—</span>
                <span class="small text-secondary"><?= e(__('reports.subscriptions.savings_none')) ?></span>
            </div>
        </div>
    </div>

    <div class="surface-card mb-4">
        <h2 class="h5 mb-3"><?= e(__('reports.subscriptions.list_title')) ?></h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th></th>
                        <th><?= e(__('reports.subscriptions.name')) ?></th>
                        <th><?= e(__('reports.subscriptions.category')) ?></th>
                        <th><?= e(__('reports.subscriptions.frequency')) ?></th>
                        <th class="text-end"><?= e(__('reports.subscriptions.amount')) ?></th>
                        <th class="text-end"><?= e(__('reports.subscriptions.annualized')) ?></th>
                        <th><?= e(__('reports.subscriptions.next')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $subscription): ?>
                        <tr>
                            <td>
                                <input class="form-check-input" type="checkbox" data-subscription
                                       value="<?= (int) $subscription['annual_centavos'] ?>"
                                       aria-label="<?= e((string) $subscription['name']) ?>">
                            </td>
                            <td>
                                <span class="fw-semibold d-block"><?= e((string) $subscription['name']) ?></span>
                                <?php if ((int) $subscription['count'] > 1): ?>
                                    <span class="small text-secondary">
                                        <?= e(__('reports.subscriptions.grouped', [':count' => (string) $subscription['count']])) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($subscription['variable'])): ?>
                                    <span class="badge text-bg-light border"><?= e(__('reports.subscriptions.variable')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary"><?= e((string) $subscription['category']) ?></td>
                            <td class="small">
                                <?= e(__('reports.frequencies.' . (string) $subscription['frequency'])) ?>
                            </td>
                            <td class="text-end"><?= money_html((int) $subscription['monthly_centavos'], $currency) ?></td>
                            <td class="text-end"><?= money_html((int) $subscription['annual_centavos'], $currency) ?></td>
                            <td class="small text-secondary">
                                <?= !empty($subscription['next']) ? e(format_date($subscription['next'])) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="surface-card">
        <h2 class="h5 mb-2"><?= e(__('reports.subscriptions.savings_title')) ?></h2>
        <p class="small text-secondary mb-3"><?= e(__('reports.subscriptions.savings_hint')) ?></p>
        <p class="mb-0" data-subscription-message><?= e(__('reports.subscriptions.savings_none')) ?></p>
    </div>
<?php endif; ?>
