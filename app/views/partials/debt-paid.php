<?php

/**
 * Festejo al liquidar una deuda. Lo encola DebtPaidFxService y lo anima
 * assets/js/89-debt-paid.js: la cadena se rompe, cae el sello de «Pagada» y
 * hay fuegos artificiales; si ya no queda ninguna deuda, la fiesta es mayor.
 */

use Cauce\Helpers\I18n;

$debtPaid = $debtPaid ?? null;

if (!is_array($debtPaid) || ($debtPaid['names'] ?? []) === []) {
    return;
}

$names = array_map('strval', $debtPaid['names']);
$count = count($names);
$remaining = (int) $debtPaid['remaining'];
$debtFree = $remaining === 0;

$eyebrows = I18n::get('debts.paid_fx.eyebrow');
$eyebrows = is_array($eyebrows) && $eyebrows !== [] ? array_values($eyebrows) : [''];
$eyebrow = (string) $eyebrows[random_int(0, count($eyebrows) - 1)];

$title = $debtFree
    ? __('debts.paid_fx.free_title')
    : ($count > 1 ? __('debts.paid_fx.title_many', [':count' => (string) $count]) : __('debts.paid_fx.title'));

// La traducción trae <strong>; los nombres van escapados.
$body = $count > 1
    ? __('debts.paid_fx.body_many', [':names' => implode(', ', array_map('e', $names))])
    : __('debts.paid_fx.body', [':name' => e($names[0])]);

$next = match (true) {
    $debtFree => __('debts.paid_fx.free'),
    $remaining === 1 => __('debts.paid_fx.remaining_one'),
    default => __('debts.paid_fx.remaining', [':count' => (string) $remaining]),
};

$stats = [];

if ($count === 1 && (int) $debtPaid['total_centavos'] > 0) {
    $stats[] = ['icon' => 'bi-cash-stack', 'label' => __('debts.paid_fx.total'),
        'value' => money_html((int) $debtPaid['total_centavos'], (string) $debtPaid['currency'])];
}

if ($count === 1 && (int) $debtPaid['payments'] > 0) {
    $stats[] = ['icon' => 'bi-receipt', 'label' => __('debts.paid_fx.payments'),
        'value' => e((string) (int) $debtPaid['payments'])];
}

if ($count === 1 && (int) $debtPaid['days'] > 0) {
    $stats[] = ['icon' => 'bi-calendar-check', 'label' => __('debts.paid_fx.days'),
        'value' => e((string) (int) $debtPaid['days'])];
}
?>
<div class="debt-paid<?= $debtFree ? ' debt-paid-free' : '' ?>" data-debt-paid="<?= $debtFree ? 'free' : 'paid' ?>"
     role="dialog" aria-modal="true" aria-labelledby="debtPaidTitle" aria-describedby="debtPaidBody">
    <div class="debt-paid-backdrop" data-debt-paid-close></div>
    <div class="debt-paid-card">
        <button type="button" class="btn-close debt-paid-dismiss" data-debt-paid-close
                aria-label="<?= e(__('debts.paid_fx.dismiss')) ?>"></button>

        <div class="debt-paid-stage" aria-hidden="true">
            <span class="debt-paid-rays"></span>
            <span class="debt-paid-ring"></span>
            <span class="debt-paid-ring debt-paid-ring-2"></span>

            <svg class="debt-paid-chain debt-paid-chain-left" viewBox="0 0 120 40">
                <rect x="4" y="10" width="44" height="20" rx="10"/>
                <rect x="36" y="10" width="44" height="20" rx="10"/>
                <path d="M68 10h22a10 10 0 0 1 10 10" />
            </svg>
            <svg class="debt-paid-chain debt-paid-chain-right" viewBox="0 0 120 40">
                <path d="M20 20a10 10 0 0 0 10 10h22" />
                <rect x="40" y="10" width="44" height="20" rx="10"/>
                <rect x="72" y="10" width="44" height="20" rx="10"/>
            </svg>
            <span class="debt-paid-spark debt-paid-spark-1"></span>
            <span class="debt-paid-spark debt-paid-spark-2"></span>
            <span class="debt-paid-spark debt-paid-spark-3"></span>

            <span class="debt-paid-seal">
                <i class="bi <?= $debtFree ? 'bi-trophy-fill' : 'bi-check-lg' ?>"></i>
            </span>
            <span class="debt-paid-stamp"><?= e(__('debts.paid_fx.stamp')) ?></span>
        </div>

        <span class="debt-paid-eyebrow"><?= e($eyebrow) ?></span>
        <h2 class="debt-paid-title" id="debtPaidTitle"><?= e($title) ?></h2>
        <p class="debt-paid-body" id="debtPaidBody"><?= $body ?></p>

        <?php if ($stats !== []): ?>
            <ul class="debt-paid-stats">
                <?php foreach ($stats as $i => $stat): ?>
                    <li style="--i: <?= (int) $i ?>">
                        <i class="bi <?= e($stat['icon']) ?>" aria-hidden="true"></i>
                        <strong><?= $stat['value'] ?></strong>
                        <span><?= e($stat['label']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p class="debt-paid-next">
            <i class="bi <?= $debtFree ? 'bi-stars' : 'bi-signpost-2' ?>" aria-hidden="true"></i>
            <span><?= e($next) ?></span>
        </p>

        <button type="button" class="btn btn-primary w-100 debt-paid-continue" data-debt-paid-close>
            <?= e(__('debts.paid_fx.continue')) ?>
        </button>
    </div>
</div>
