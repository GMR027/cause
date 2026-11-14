<?php
$name = $name ?? 'moneda';
$selected = strtoupper((string) ($selected ?? ''));
$label = $label ?? __('currency.label');
$id = $id ?? 'currency-' . preg_replace('/[^a-z0-9_]/', '-', $name);
$showLabel = $showLabel ?? true;
$options = $currencies ?? currency_options();

if ($selected === '') {
    foreach ($options as $option) {
        if (!empty($option['es_principal'])) {
            $selected = (string) $option['codigo'];
            break;
        }
    }
}
?>
<?php if ($showLabel): ?>
    <label class="form-label" for="<?= e($id) ?>"><?= e($label) ?></label>
<?php endif; ?>
<select class="form-select" id="<?= e($id) ?>" name="<?= e($name) ?>">
    <?php foreach ($options as $option): ?>
        <?php $code = (string) $option['codigo']; ?>
        <option value="<?= e($code) ?>" <?= $selected === $code ? 'selected' : '' ?>>
            <?= e($code) ?><?= !empty($option['es_principal']) ? ' · ' . e(__('currency.principal')) : '' ?>
        </option>
    <?php endforeach; ?>
</select>
