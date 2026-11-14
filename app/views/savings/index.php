<?php


$metas = $metas ?? [];
$accounts = $accounts ?? [];
$defaultAccountId = (int) ($default_account_id ?? 0);
$transfers = $transfers ?? [];
$emergency = $emergency ?? [];
$currency = (string) ($currency ?? 'MXN');
$timezone = new DateTimeZone((string) ($currentUser['zona_horaria'] ?? config('app.timezone', 'UTC')));
$today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
$localDate = static function (string $value) use ($timezone): string {
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone($timezone)->format('Y-m-d');
};
$emergencyMeta = $emergency['meta'] ?? [];
$emergencyId = (int) ($emergencyMeta['id'] ?? 0);
$metaTypes = $meta_types ?? [];
$withdrawReasons = $withdraw_reasons ?? [];
$activeMetas = array_values(array_filter($metas, static fn (array $m): bool => (int) $m['activa'] === 1));
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('savings.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('savings.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('savings.subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?= partial('partials/lesson', ['lesson' => lesson_for('ahorro')]) ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#savingsContribute">
            <?= e(__('savings.actions.contribute')) ?>
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#savingsWithdraw">
            <?= e(__('savings.actions.withdraw')) ?>
        </button>
    </div>
</div>

<?php if (!empty($emergency['notify'])): ?>
    <div class="finance-warning mb-3" data-finance-warning>
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
        <div class="flex-grow-1">
            <strong class="d-block"><?= e(__('savings.emergency.notice_title')) ?></strong>
            <span class="small">
                <?= e(__('savings.emergency.notice_body', [
                    ':current' => format_money((int) ($emergency['current'] ?? 0), $currency),
                    ':suggested' => format_money((int) ($emergency['suggested'] ?? 0), $currency),
                    ':percent' => (string) ($emergency['change_percent'] ?? 0),
                ])) ?>
            </span>
        </div>
        <form method="post" action="/ahorro/emergencia" class="m-0">
            <?= csrf_field() ?>
            <input type="hidden" name="meta_id" value="<?= $emergencyId ?>">
            <button type="submit" class="btn btn-sm btn-primary"><?= e(__('savings.emergency.update')) ?></button>
        </form>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss-warning>
            <?= e(__('savings.emergency.keep')) ?>
        </button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('savings.summary.total')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($total_centavos ?? 0), $currency) ?></span>
            <span class="small text-secondary"><?= e(__('savings.summary.total_hint')) ?></span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('savings.summary.emergency')) ?></span>
            <span class="finance-card-value">
                <?= money_html((int) ($emergency_saved_centavos ?? 0), $currency) ?>
            </span>
            <?php $ep = (int) ($emergencyMeta['objetivo_centavos'] ?? 0); $ea = (int) ($emergency_saved_centavos ?? 0); ?>
            <span class="finance-progress" aria-hidden="true">
                <span style="width: <?= $ep > 0 ? min(100, (int) round(($ea / $ep) * 100)) : 0 ?>%"></span>
            </span>
            <span class="small text-secondary">
                <?= e(__('savings.summary.of_goal', [':goal' => format_money($ep, $currency)])) ?>
            </span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card finance-card-accent">
            <span class="finance-card-label"><?= e(__('finance.available')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($available_centavos ?? 0), $currency) ?></span>
            <span class="small text-secondary">
                <?= e(__('savings.summary.available_hint', [
                    ':assigned' => format_money((int) ($assigned_centavos ?? 0), $currency),
                ])) ?>
            </span>
            <?php if (!empty($over_percentage)): ?>
                <span class="badge text-bg-warning mt-1"><?= e(__('finance.over_percentage')) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-5">
        <div class="surface-card h-100">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <h2 class="h5 mb-0"><?= e(__('savings.emergency.title')) ?></h2>
                <div class="d-flex align-items-center gap-2">
                    <?= partial('partials/lesson', ['lesson' => lesson_for('fondo_emergencia')]) ?>
                    <i class="bi bi-shield-check text-primary" aria-hidden="true"></i>
                </div>
            </div>
            <p class="small text-secondary mb-3"><?= e(__('savings.emergency.subtitle')) ?></p>
            <div class="finance-progress mb-2" aria-hidden="true">
                <span style="width: <?= $ep > 0 ? min(100, (int) round(($ea / $ep) * 100)) : 0 ?>%"></span>
            </div>
            <div class="d-flex justify-content-between small">
                <span><?= money_html($ea, $currency) ?></span>
                <span class="text-secondary"><?= money_html($ep, $currency) ?></span>
            </div>
            <p class="small text-secondary mt-2 mb-0">
                <?= e(__('savings.emergency.average', [
                    ':average' => format_money((int) ($emergency['suggested'] ?? 0) / 3, $currency),
                ])) ?>
            </p>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="surface-card h-100">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h5 mb-0"><?= e(__('savings.accounts.title')) ?></h2>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-account-transfer
                            data-bs-toggle="offcanvas" data-bs-target="#savingsTransfer">
                        <?= e(__('savings.accounts.transfer')) ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-meta-assign
                            data-bs-toggle="offcanvas" data-bs-target="#savingsAssign">
                        <?= e(__('savings.accounts.assign')) ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" data-account-new
                            data-bs-toggle="offcanvas" data-bs-target="#savingsAccount">
                        <?= e(__('savings.accounts.create')) ?>
                    </button>
                </div>
            </div>
            <p class="small text-secondary mb-3"><?= e(__('savings.accounts.hint')) ?></p>

            <?php if ($accounts === []): ?>
                <p class="text-secondary small mb-0"><?= e(__('savings.accounts.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($accounts as $account): ?>
                        <div class="finance-row align-items-start gap-2">
                            <?= partial('partials/cover', [
                                'target' => 'bolsa',
                                'id' => (int) $account['id'],
                                'path' => $account['portada_path'] ?? null,
                                'name' => (string) $account['nombre'],
                                'icon' => 'piggy-bank',
                                'small' => true,
                            ]) ?>
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block">
                                    <?= e((string) $account['nombre']) ?>
                                    <?php if ((int) $account['es_predeterminada'] === 1): ?>
                                        <span class="badge text-bg-light border ms-1"><?= e(__('savings.accounts.default')) ?></span>
                                    <?php endif; ?>
                                    <?php if ((int) $account['activa'] !== 1): ?>
                                        <span class="badge text-bg-secondary ms-1"><?= e(__('savings.accounts.archived')) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="small text-secondary d-block">
                                    <?= e(__('savings.accounts.type_' . (string) $account['tipo'])) ?>
                                    <?php if (!empty($account['institucion'])): ?>
                                        · <?= e((string) $account['institucion']) ?>
                                    <?php endif; ?>
                                    · <?= e(__('savings.accounts.metas_count', [':count' => (string) (int) $account['metas_count']])) ?>
                                </span>
                                <span class="small text-secondary d-block">
                                    <?= e(__('savings.accounts.assigned', [
                                        ':assigned' => format_money((int) $account['asignado_principal_centavos'], $currency),
                                        ':free' => format_money((int) $account['sin_asignar_principal_centavos'], $currency),
                                    ])) ?>
                                </span>
                            </div>
                            <div class="text-end">
                                <span class="fw-semibold d-block"><?= money_html((int) $account['saldo_principal_centavos'], $currency) ?></span>
                                <div class="d-flex gap-1 mt-2 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-account-edit
                                            data-account-id="<?= (int) $account['id'] ?>"
                                            data-account-name="<?= e((string) $account['nombre']) ?>"
                                            data-account-type="<?= e((string) $account['tipo']) ?>"
                                            data-account-institution="<?= e((string) ($account['institucion'] ?? '')) ?>"
                                            data-account-default="<?= (int) $account['es_predeterminada'] ?>"
                                            data-account-active="<?= (int) $account['activa'] ?>"
                                            data-bs-toggle="offcanvas" data-bs-target="#savingsAccount">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </button>
                                    <?php if ((int) $account['activa'] === 1 && (int) $account['es_predeterminada'] !== 1): ?>
                                        <form method="post" action="/ahorro/cuentas/archivar" class="m-0"
                                              onsubmit="return confirm('<?= e(__('savings.accounts.confirm_archive')) ?>')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-archive" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2 mt-2">
    <h2 class="h5 mb-0"><?= e(__('savings.meta.title')) ?></h2>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-meta-new
            data-bs-toggle="offcanvas" data-bs-target="#savingsMeta">
        <?= e(__('savings.meta.create')) ?>
    </button>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($metas as $meta): ?>
        <?php if ((int) $meta['es_emergencia'] === 1) { continue; } ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="goal-card <?= (int) $meta['activa'] === 1 ? '' : 'is-inactive' ?>">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <div class="card-head">
                        <?= partial('partials/cover', [
                            'target' => 'meta',
                            'id' => (int) $meta['id'],
                            'path' => $meta['portada_path'] ?? null,
                            'name' => (string) $meta['nombre'],
                            'icon' => (string) $meta['tipo'] === 'deseo' ? 'gift' : 'bullseye',
                        ]) ?>
                        <div class="card-head-body">
                        <span class="fw-semibold d-block"><?= e((string) $meta['nombre']) ?></span>
                        <span class="small text-secondary">
                            <?= e(__('savings.types.' . (string) $meta['tipo'])) ?>
                            <?php if (!empty($meta['cuenta_nombre'])): ?>
                                · <?= e((string) $meta['cuenta_nombre']) ?>
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($meta['cumplida'])): ?>
                            <span class="badge text-bg-success mt-1"><?= e(__('savings.meta.completed')) ?></span>
                        <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-meta-edit
                                data-meta-id="<?= (int) $meta['id'] ?>"
                                data-meta-name="<?= e((string) $meta['nombre']) ?>"
                                data-meta-type="<?= e((string) $meta['tipo']) ?>"
                                data-meta-objective="<?= e(number_format((int) $meta['objetivo_centavos'] / 100, 2, '.', '')) ?>"
                                data-meta-date="<?= e((string) ($meta['fecha_objetivo'] ?? '')) ?>"
                                data-meta-months="<?= (int) ($meta['plazo_meses'] ?? 0) ?>"
                                data-meta-active="<?= (int) $meta['activa'] ?>"
                                data-meta-account="<?= (int) ($meta['cuenta_id'] ?? 0) ?>"
                                data-bs-toggle="offcanvas" data-bs-target="#savingsMeta">
                            <i class="bi bi-pencil" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-meta-move
                                data-meta-id="<?= (int) $meta['id'] ?>"
                                data-meta-name="<?= e((string) $meta['nombre']) ?>"
                                data-bs-toggle="offcanvas" data-bs-target="#savingsMoveMeta">
                            <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <span class="goal-amount"><?= money_html((int) $meta['acumulado_centavos'], (string) ($meta['moneda'] ?? $currency)) ?></span>
                <span class="small text-secondary d-block mb-2">
                    <?= e(__('savings.meta.of', [':goal' => format_money((int) $meta['objetivo_centavos'], (string) ($meta['moneda'] ?? $currency))])) ?>
                </span>
                <div class="finance-progress mb-2" aria-hidden="true">
                    <span style="width: <?= (int) $meta['progreso'] ?>%"></span>
                </div>
                <div class="d-flex justify-content-between small">
                    <span><?= (int) $meta['progreso'] ?>%</span>
                    <span class="text-secondary">
                        <?= e(__('savings.meta.monthly', [
                            ':amount' => $meta['sugerido_mensual_centavos'] === null
                                ? '—'
                                : format_money((int) $meta['sugerido_mensual_centavos'], (string) ($meta['moneda'] ?? $currency)),
                        ])) ?>
                    </span>
                </div>
                <div class="d-flex flex-wrap justify-content-between gap-2 small text-secondary mt-1">
                    <?php if (!empty($meta['fecha_objetivo'])): ?>
                        <span><?= e(__('savings.meta.target_date_short', [
                            ':date' => format_date($meta['fecha_objetivo'] . ' 12:00:00'),
                        ])) ?></span>
                    <?php elseif ($meta['plazo_restante_meses'] !== null): ?>
                        <span><?= e(__('savings.meta.remaining', [
                            ':months' => (string) $meta['plazo_restante_meses'],
                        ])) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ((int) $meta['activa'] !== 1): ?>
                    <span class="badge text-bg-secondary mt-2"><?= e(__('savings.meta.inactive')) ?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="surface-card">
            <h2 class="h5 mb-3"><?= e(__('savings.movements.contributions')) ?></h2>
            <?php if (($contributions ?? []) === []): ?>
                <p class="text-secondary small mb-0"><?= e(__('savings.movements.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($contributions as $row): ?>
                        <div class="finance-row">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block"><?= money_html((int) $row['monto_centavos'], (string) ($row['moneda'] ?? $currency)) ?></span>
                                <span class="small text-secondary">
                                    <?= e((string) ($row['cuenta_nombre'] ?? __('savings.accounts.title'))) ?>
                                    <?php if (!empty($row['meta_nombre'])): ?>
                                        · <?= e((string) $row['meta_nombre']) ?>
                                    <?php endif; ?>
                                    <?php if ((int) ($row['es_saldo_inicial'] ?? 0) === 1): ?>
                                        · <span class="badge text-bg-light border"><?= e(__('savings.movements.initial')) ?></span>
                                    <?php endif; ?>
                                    · <?= e(format_date($row['fecha'] ?? null)) ?>
                                </span>
                            </div>
                            <?php if (!empty($row['editable'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-contribution-edit
                                        data-id="<?= (int) $row['id'] ?>"
                                        data-amount="<?= e(number_format((int) $row['monto_centavos'] / 100, 2, '.', '')) ?>"
                                        data-date="<?= e($localDate((string) $row['fecha'])) ?>"
                                        data-bs-toggle="offcanvas" data-bs-target="#savingsEditContribution">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <form method="post" action="/ahorro/aportaciones/eliminar" class="m-0"
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

    <div class="col-12 col-xl-6">
        <div class="surface-card">
            <h2 class="h5 mb-3"><?= e(__('savings.movements.withdrawals')) ?></h2>
            <?php if (($withdrawals ?? []) === []): ?>
                <p class="text-secondary small mb-0"><?= e(__('savings.movements.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($withdrawals as $row): ?>
                        <div class="finance-row">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block"><?= money_html((int) $row['monto_centavos'], (string) ($row['moneda'] ?? $currency)) ?></span>
                                <span class="small text-secondary">
                                    <?= e((string) ($row['cuenta_nombre'] ?? __('savings.accounts.title'))) ?>
                                    <?php if (!empty($row['meta_nombre'])): ?>
                                        · <?= e((string) $row['meta_nombre']) ?>
                                    <?php endif; ?>
                                    · <?= e(__('savings.reasons.' . (string) $row['motivo_catalogo'])) ?>
                                    · <?= e(format_date($row['fecha'] ?? null)) ?>
                                </span>
                            </div>
                            <?php if (!empty($row['editable'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-withdrawal-edit
                                        data-id="<?= (int) $row['id'] ?>"
                                        data-amount="<?= e(number_format((int) $row['monto_centavos'] / 100, 2, '.', '')) ?>"
                                        data-date="<?= e($localDate((string) $row['fecha'])) ?>"
                                        data-reason="<?= e((string) $row['motivo_catalogo']) ?>"
                                        data-text="<?= e((string) ($row['motivo_texto'] ?? '')) ?>"
                                        data-bs-toggle="offcanvas" data-bs-target="#savingsEditWithdrawal">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <form method="post" action="/ahorro/retiros/eliminar" class="m-0"
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

<?= partial('savings/sheets', [
    'metas' => $metas,
    'activeMetas' => $activeMetas,
    'accounts' => $accounts,
    'default_account_id' => $defaultAccountId,
    'metaTypes' => $metaTypes,
    'withdrawReasons' => $withdrawReasons,
    'currency' => $currency,
    'today' => $today,
]) ?>
