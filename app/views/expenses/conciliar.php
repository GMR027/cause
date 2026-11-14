<?php

$pending = $pending ?? [];
$hasIncome = (bool) ($hasIncome ?? false);
$currency = (string) ($currentUser['moneda_activa'] ?? 'MXN');
$total = array_sum(array_map(
    static fn (array $row): int => (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']),
    $pending
));
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('expenses.conciliar.heading')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('expenses.conciliar.subtitle')) ?></p>
    </div>
    <a href="/inicio" class="btn btn-outline-secondary d-none d-xl-inline-flex"><?= e(__('common.actions.back')) ?></a>
</div>

<?php if (!$hasIncome): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
        <span class="flex-grow-1"><?= e(__('expenses.conciliar.no_income')) ?></span>
        <a href="/ingresos/nuevo" class="btn btn-sm btn-primary"><?= e(__('expenses.conciliar.go_income')) ?></a>
    </div>
<?php endif; ?>

<?php if ($pending === []): ?>
    <div class="surface-card">
        <div class="empty-state">
            <span class="empty-icon" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
            <h2 class="h5 mb-1"><?= e(__('expenses.conciliar.empty_title')) ?></h2>
            <p class="text-secondary mb-0"><?= e(__('expenses.conciliar.empty_body')) ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="surface-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <span class="small text-secondary d-block"><?= e(__('expenses.conciliar.pending_total')) ?></span>
                <span class="h5 mb-0 d-block"><?= money_html($total, $currency) ?></span>
            </div>

            <?php if ($hasIncome): ?>
                <form method="post" action="/gastos/conciliar/todos"
                      onsubmit="return confirm('<?= e(__('expenses.conciliar.confirm_all')) ?>')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary"><?= e(__('expenses.conciliar.all')) ?></button>
                </form>
            <?php endif; ?>
        </div>

        <div class="conciliar-list">
            <?php foreach ($pending as $row): ?>
                <div class="conciliar-row">
                    <div>
                        <span class="fw-semibold"><?= money_html((int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']), $currency) ?></span>
                        <span class="small text-secondary d-block">
                            <?= e((string) $row['parte_metodo']) ?> ·
                            <?= e(format_date($row['fecha'] ?? null)) ?>
                        </span>
                    </div>

                    <form method="post" action="/gastos/conciliar/uno" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary" <?= $hasIncome ? '' : 'disabled' ?>>
                            <?= e(__('expenses.conciliar.one')) ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="small text-secondary mt-3 mb-0"><?= e(__('expenses.conciliar.hint')) ?></p>
    </div>
<?php endif; ?>
