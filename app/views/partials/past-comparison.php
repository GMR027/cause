<?php

$past = $past ?? [];
$currency = (string) ($currency ?? 'MXN');
$pct = static fn (float $value): string => rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
$direction = (string) ($past['direction'] ?? 'none');
$raw = false;

if ($direction === 'same') {
    $text = __('gamification.comparison.same');
} elseif (!empty($past['comparable'])) {
    $key = $direction === 'up'
        ? 'gamification.comparison.up'
        : 'gamification.comparison.down';
    $text = __($key, [':percent' => $pct(abs((float) ($past['percent'] ?? 0)))]);
} elseif ($direction === 'up' || $direction === 'down') {
    $key = $direction === 'up'
        ? 'gamification.comparison.amount_up'
        : 'gamification.comparison.amount_down';
    $text = __($key, [':amount' => money_html(abs((int) ($past['delta'] ?? 0)), $currency)]);
    $raw = true;
} else {
    $text = __('gamification.comparison.no_base');
}
?>
<div class="progress-past">
    <span class="progress-past-label"><?= e(__('gamification.comparison.title')) ?></span>
    <p class="progress-past-text mb-1"><?= $raw ? $text : e($text) ?></p>
    <p class="small text-secondary mb-0">
        <?= __('gamification.comparison.current', [
            ':amount' => money_html((int) ($past['current_centavos'] ?? 0), $currency),
        ]) ?>
    </p>
</div>
