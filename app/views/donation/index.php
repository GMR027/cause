<?php


$currency = (string) ($currency ?? 'MXN');
$timezone = new DateTimeZone((string) ($currentUser['zona_horaria'] ?? config('app.timezone', 'UTC')));
$today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
$localDate = static function (string $value) use ($timezone): string {
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone($timezone)->format('Y-m-d');
};
$recent = $recent ?? [];
$byCause = $by_cause ?? [];
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('donation.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('donation.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('donation.subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?= partial('partials/lesson', ['lesson' => lesson_for('donacion')]) ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#donationDonate">
            <?= e(__('donation.actions.donate')) ?>
        </button>
        <?php if (empty($has_category)): ?>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="offcanvas"
                    data-bs-target="#donationActivate">
                <?= e(__('donation.actions.activate')) ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($has_category)): ?>
    <div class="finance-warning mb-3">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <div class="flex-grow-1">
            <strong class="d-block"><?= e(__('donation.no_category_title')) ?></strong>
            <span class="small"><?= e(__('donation.no_category_body')) ?></span>
        </div>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="offcanvas"
                data-bs-target="#donationActivate">
            <?= e(__('donation.actions.activate')) ?>
        </button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('donation.summary.period')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($period_total_centavos ?? 0), $currency) ?></span>
            <span class="small text-secondary"><?= e(__('donation.summary.period_hint')) ?></span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('donation.summary.total')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($total_centavos ?? 0), $currency) ?></span>
            <span class="small text-secondary"><?= e(__('donation.summary.total_hint')) ?></span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card finance-card-accent">
            <span class="finance-card-label"><?= e(__('finance.available')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($available_centavos ?? 0), $currency) ?></span>
            <span class="small text-secondary">
                <?= e(__('donation.summary.available_hint', [
                    ':percent' => (string) round((float) ($percent ?? 0), 2),
                    ':assigned' => format_money((int) ($assigned_centavos ?? 0), $currency),
                ])) ?>
            </span>
            <?php if (!empty($over_percentage)): ?>
                <span class="badge text-bg-warning mt-1"><?= e(__('finance.over_percentage')) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('donation.by_cause')) ?></h2>
            <?php if ($byCause === []): ?>
                <p class="small text-secondary mb-0"><?= e(__('donation.no_causes')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($byCause as $cause): ?>
                        <div class="finance-row">
                            <span class="flex-grow-1"><?= e((string) $cause['causa']) ?></span>
                            <span class="fw-semibold"><?= money_html((int) $cause['total'], $currency) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('donation.history')) ?></h2>
            <?php if ($recent === []): ?>
                <p class="small text-secondary mb-0"><?= e(__('donation.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($recent as $row): ?>
                        <div class="finance-row">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block"><?= money_html((int) $row['monto_centavos'], $currency) ?></span>
                                <span class="small text-secondary">
                                    <?= e((string) ($row['causa'] ?? __('donation.no_cause'))) ?>
                                    · <?= e(format_date($row['fecha'] ?? null)) ?>
                                    <?php if ((int) $row['es_extra'] === 1): ?>
                                        · <?= e(__('donation.extra_short')) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($row['editable'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-donation-edit
                                        data-id="<?= (int) $row['id'] ?>"
                                        data-amount="<?= e(number_format((int) $row['monto_centavos'] / 100, 2, '.', '')) ?>"
                                        data-date="<?= e($localDate((string) $row['fecha'])) ?>"
                                        data-cause="<?= e((string) ($row['causa'] ?? '')) ?>"
                                        data-extra="<?= (int) $row['es_extra'] ?>"
                                        data-bs-toggle="offcanvas" data-bs-target="#donationEdit">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <form method="post" action="/donacion/eliminar" class="m-0"
                                      onsubmit="return confirm('<?= e(__('finance.confirm_delete')) ?>')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= partial('donation/sheets', [
    'currency' => $currency,
    'today' => $today,
]) ?>

<?= partial('donation/celebration', [
    'celebration' => $celebration ?? null,
    'currency' => $currency,
]) ?>
