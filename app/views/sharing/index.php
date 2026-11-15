<?php

$currency = (string) ($currency ?? 'MXN');
$timezone = new DateTimeZone((string) ($currentUser['zona_horaria'] ?? config('app.timezone', 'UTC')));
$today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
$localDate = static function (string $value) use ($timezone): string {
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone($timezone)->format('Y-m-d');
};
$recent = $recent ?? [];
$byRecipient = $by_recipient ?? [];
$assigned = (int) ($assigned_centavos ?? 0);
$normal = (int) ($normal_centavos ?? 0);
$progress = $assigned > 0 ? min(100, (int) round(($normal / $assigned) * 100)) : 0;
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('sharing.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('sharing.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('sharing.subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#sharingCreate">
            <?= e(__('sharing.actions.create')) ?>
        </button>
    </div>
</div>

<?php if (empty($has_category)): ?>
    <div class="finance-warning mb-3" data-dismissible data-dismiss-key="sharing-no-category">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <div class="flex-grow-1">
            <strong class="d-block"><?= e(__('sharing.no_category_title')) ?></strong>
            <span class="small"><?= e(__('sharing.no_category_body')) ?></span>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('sharing.summary.period')) ?></span>
            <span class="finance-card-value"><?= money_html($normal, $currency) ?></span>
            <?php if ($assigned > 0): ?>
                <div class="progress mt-2" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"
                     aria-label="<?= e(__('sharing.summary.progress', [':percent' => (string) $progress])) ?>" style="height: 6px;">
                    <div class="progress-bar <?= !empty($completed) ? 'bg-success' : '' ?>" style="width: <?= $progress ?>%"></div>
                </div>
            <?php endif; ?>
            <span class="small text-secondary mt-1">
                <?php if (!empty($completed)): ?>
                    <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
                    <?= e(__('sharing.summary.completed')) ?>
                <?php else: ?>
                    <?= e(__('sharing.summary.progress', [':percent' => (string) $progress])) ?>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('sharing.summary.total')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($total_centavos ?? 0), $currency) ?></span>
            <span class="small text-secondary"><?= e(__('sharing.summary.total_hint')) ?></span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card finance-card-accent">
            <span class="finance-card-label"><?= e(__('finance.available')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($available_centavos ?? 0), $currency) ?></span>
            <span class="small text-secondary">
                <?= e(__('sharing.summary.available_hint', [
                    ':percent' => (string) round((float) ($percent ?? 0), 2),
                    ':assigned' => format_money($assigned, $currency),
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
            <h2 class="h5 mb-3"><?= e(__('sharing.by_recipient')) ?></h2>
            <?php if ($byRecipient === []): ?>
                <p class="small text-secondary mb-0"><?= e(__('sharing.no_recipients')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($byRecipient as $item): ?>
                        <div class="finance-row">
                            <span class="flex-grow-1"><?= e((string) $item['destinatario']) ?></span>
                            <span class="fw-semibold"><?= money_html((int) $item['total'], $currency) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('sharing.history')) ?></h2>
            <?php if ($recent === []): ?>
                <p class="small text-secondary mb-0"><?= e(__('sharing.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($recent as $row): ?>
                        <div class="finance-row">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block"><?= money_html((int) $row['monto_centavos'], (string) $row['moneda']) ?></span>
                                <span class="small text-secondary">
                                    <?= e((string) ($row['destinatario'] ?? __('sharing.no_recipient'))) ?>
                                    · <?= e(format_date($row['fecha'] ?? null)) ?>
                                    <?php if ((int) $row['es_extra'] === 1): ?>
                                        · <?= e(__('sharing.extra_short')) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($row['editable'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-sharing-edit
                                        data-id="<?= (int) $row['id'] ?>"
                                        data-amount="<?= e(number_format((int) $row['monto_centavos'] / 100, 2, '.', '')) ?>"
                                        data-date="<?= e($localDate((string) $row['fecha'])) ?>"
                                        data-recipient="<?= e((string) ($row['destinatario'] ?? '')) ?>"
                                        data-extra="<?= (int) $row['es_extra'] ?>"
                                        data-bs-toggle="offcanvas" data-bs-target="#sharingEdit"
                                        aria-label="<?= e(__('sharing.edit')) ?>">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <form method="post" action="/compartir/eliminar" class="m-0"
                                      data-confirm="<?= e(__('finance.confirm_delete')) ?>" data-confirm-danger>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            aria-label="<?= e(__('common.actions.delete')) ?>">
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

<?= partial('sharing/sheets', [
    'currency' => $currency,
    'today' => $today,
]) ?>
