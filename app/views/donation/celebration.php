<?php

/**
 * Festejo tras registrar una donación: agradecimiento siempre, récords con
 * confeti cuando la gamificación está activa. Las partículas las añade
 * assets/js/86-donation-fx.js, que también muestra y retira la tarjeta.
 */

use Cauce\Helpers\I18n;

$celebration = $celebration ?? null;

if (!is_array($celebration)) {
    return;
}

$gamificationEnabled = $gamificationEnabled ?? true;
$currency = (string) ($currency ?? 'MXN');
$allowed = ['first', 'biggest', 'best_month', 'milestone'];
$records = $gamificationEnabled
    ? array_values(array_intersect((array) ($celebration['records'] ?? []), $allowed))
    : [];
$isRecord = $records !== [];
$count = max(1, (int) ($celebration['count'] ?? 1));

$thanks = I18n::get('donation.celebration.thanks');
$thanks = is_array($thanks) && $thanks !== [] ? array_values($thanks) : [__('donation.flash.donated')];
$phrase = (string) $thanks[random_int(0, count($thanks) - 1)];

// Los montos van como HTML (respetan el modo discreto); el resto del texto se escapa.
$body = e(__($count === 1 ? 'donation.celebration.body_one' : 'donation.celebration.body', [
    ':amount' => '%%AMOUNT%%',
    ':total' => '%%TOTAL%%',
    ':count' => (string) $count,
]));
$body = strtr($body, [
    '%%AMOUNT%%' => '<strong>' . money_html(
        (int) ($celebration['amount_centavos'] ?? 0),
        (string) ($celebration['currency'] ?? $currency)
    ) . '</strong>',
    '%%TOTAL%%' => '<strong>' . money_html((int) ($celebration['total_centavos'] ?? 0), $currency) . '</strong>',
]);
$recordIcons = [
    'first' => 'bi-stars',
    'biggest' => 'bi-gem',
    'best_month' => 'bi-calendar-heart',
    'milestone' => 'bi-flag-fill',
];
?>
<div class="donation-fx <?= $isRecord ? 'donation-fx-record' : 'donation-fx-thanks' ?>"
     data-donation-fx="<?= $isRecord ? 'record' : 'thanks' ?>"
     <?php if ($isRecord): ?>
         role="dialog" aria-modal="true" aria-labelledby="donationFxTitle"
     <?php else: ?>
         role="status" aria-live="polite"
     <?php endif; ?>>
    <?php if ($isRecord): ?>
        <div class="donation-fx-backdrop" data-donation-fx-close></div>
    <?php endif; ?>
    <div class="donation-fx-card">
        <button type="button" class="btn-close donation-fx-dismiss" data-donation-fx-close
                aria-label="<?= e(__('donation.celebration.dismiss')) ?>"></button>
        <div class="donation-fx-icon" aria-hidden="true">
            <i class="bi <?= $isRecord ? 'bi-trophy-fill' : 'bi-heart-fill' ?>"></i>
        </div>
        <div class="donation-fx-text">
            <?php if ($isRecord): ?>
                <span class="donation-fx-eyebrow"><?= e($phrase) ?></span>
                <h2 class="h4 mb-1" id="donationFxTitle"><?= e(__('donation.celebration.record_title')) ?></h2>
            <?php else: ?>
                <h2 class="h6 mb-1" id="donationFxTitle"><?= e($phrase) ?></h2>
            <?php endif; ?>
            <p class="small text-secondary mb-0"><?= $body ?></p>
        </div>
        <?php if ($isRecord): ?>
            <ul class="donation-fx-records">
                <?php foreach ($records as $i => $record): ?>
                    <li style="--i: <?= (int) $i ?>">
                        <i class="bi <?= e($recordIcons[$record]) ?>" aria-hidden="true"></i>
                        <span><?= e(__('donation.celebration.records.' . $record, [':count' => (string) $count])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn btn-primary w-100" data-donation-fx-close>
                <?= e(__('donation.celebration.close')) ?>
            </button>
        <?php endif; ?>
        <?php if (!$isRecord): ?>
            <span class="donation-fx-timer" aria-hidden="true"></span>
        <?php endif; ?>
    </div>
</div>
