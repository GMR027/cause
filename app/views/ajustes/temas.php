<?php

$theme = $theme ?? [];
$palette = $palette ?? [];
$errors = $errors ?? [];
$color = (string) ($theme['color'] ?? '#157f6b');
$isCustom = !in_array($color, $palette, true);
$modes = [
    'claro' => ['icon' => 'bi-sun', 'label' => 'settings.temas.mode_claro'],
    'oscuro' => ['icon' => 'bi-moon-stars', 'label' => 'settings.temas.mode_oscuro'],
    'auto' => ['icon' => 'bi-circle-half', 'label' => 'settings.temas.mode_auto'],
];
$focus = [
    'iconos' => 'settings.temas.focus_iconos',
    'botones' => 'settings.temas.focus_botones',
    'mixto' => 'settings.temas.focus_mixto',
];
$backgrounds = [
    'ninguno' => 'settings.temas.bg_none',
    'radial' => 'settings.temas.bg_radial',
    'textura' => 'settings.temas.bg_texture',
    'radial_textura' => 'settings.temas.bg_radial_texture',
];
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('settings.temas.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('settings.temas.subtitle')) ?></p>
    </div>
    <a href="/ajustes" class="btn btn-outline-secondary d-none d-xl-inline-flex"><?= e(__('common.actions.back')) ?></a>
</div>

<?= partial('partials/errors', ['errors' => $errors]) ?>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <form method="post" action="/ajustes/temas" class="surface-card" data-theme-form>
            <?= csrf_field() ?>

            <h2 class="h5 mb-3"><?= e(__('settings.temas.mode')) ?></h2>
            <div class="option-grid option-grid-3 mb-4">
                <?php foreach ($modes as $mode => $meta): ?>
                    <label class="option-card">
                        <input type="radio" name="modo" value="<?= e($mode) ?>"
                               <?= ($theme['modo'] ?? 'auto') === $mode ? 'checked' : '' ?>>
                        <span class="option-body">
                            <span class="option-icon" aria-hidden="true"><i class="bi <?= e($meta['icon']) ?>"></i></span>
                            <span class="option-title"><?= e(__($meta['label'])) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <h2 class="h5 mb-2"><?= e(__('settings.temas.color')) ?></h2>
            <p class="small text-secondary mb-2"><?= e(__('settings.temas.color_hint')) ?></p>

            <div class="color-grid mb-3">
                <?php foreach ($palette as $option): ?>
                    <label class="color-swatch" title="<?= e($option) ?>">
                        <input type="radio" name="color" value="<?= e($option) ?>"
                               <?= $color === $option ? 'checked' : '' ?>>
                        <span class="color-dot" style="background-color: <?= e($option) ?>"></span>
                        <span class="visually-hidden"><?= e($option) ?></span>
                    </label>
                <?php endforeach; ?>

                <label class="color-swatch" title="<?= e(__('settings.temas.custom')) ?>">
                    <input type="radio" name="color" value="custom" <?= $isCustom ? 'checked' : '' ?>>
                    <span class="color-dot color-dot-custom" aria-hidden="true">
                        <i class="bi bi-eyedropper"></i>
                    </span>
                    <span class="visually-hidden"><?= e(__('settings.temas.custom')) ?></span>
                </label>
            </div>

            <div class="d-flex align-items-center gap-2 mb-4">
                <input type="color" class="form-control form-control-color" name="color_custom"
                       value="<?= e($color) ?>" data-theme-custom
                       aria-label="<?= e(__('settings.temas.custom')) ?>">
                <span class="small text-secondary"><?= e(__('settings.temas.custom_hint')) ?></span>
            </div>

            <h2 class="h5 mb-3"><?= e(__('settings.temas.focus')) ?></h2>
            <div class="option-grid option-grid-3 mb-4">
                <?php foreach ($focus as $value => $label): ?>
                    <label class="option-card">
                        <input type="radio" name="enfoque" value="<?= e($value) ?>"
                               <?= ($theme['enfoque'] ?? 'mixto') === $value ? 'checked' : '' ?>>
                        <span class="option-body">
                            <span class="option-title"><?= e(__($label)) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <h2 class="h5 mb-3"><?= e(__('settings.temas.background')) ?></h2>
            <div class="option-grid option-grid-2 mb-3">
                <?php foreach ($backgrounds as $value => $label): ?>
                    <label class="option-card">
                        <input type="radio" name="fondo" value="<?= e($value) ?>"
                               <?= ($theme['fondo'] ?? 'ninguno') === $value ? 'checked' : '' ?>
                               data-theme-background>
                        <span class="option-body">
                            <span class="option-title"><?= e(__($label)) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="mb-4" data-theme-texture-wrap>
                <label class="form-label small" for="textura"><?= e(__('settings.temas.texture')) ?></label>
                <select class="form-select" id="textura" name="textura">
                    <?php foreach ((array) config('themes.textures', []) as $texture): ?>
                        <option value="<?= e((string) $texture) ?>"
                                <?= ($theme['textura'] ?? 'dots') === $texture ? 'selected' : '' ?>>
                            <?= e(__('themes.textures.' . (string) $texture)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="small text-secondary mb-4" data-theme-contrast>
                <?= e(__('settings.temas.contrast_hint')) ?>
            </p>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><?= e(__('settings.temas.apply')) ?></button>
                <button type="submit" name="reset" value="1" class="btn btn-outline-secondary" formnovalidate>
                    <?= e(__('settings.temas.reset')) ?>
                </button>
            </div>
        </form>
    </div>

    <div class="col-12 col-xl-5">
        <div class="surface-card theme-preview-wrap">
            <h2 class="h5 mb-1"><?= e(__('settings.temas.preview')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('settings.temas.preview_hint')) ?></p>

            <div class="theme-preview" data-theme-preview data-bootstrap-theme="light">
                <div class="preview-top">
                    <span class="preview-jar" aria-hidden="true"><i class="bi bi-piggy-bank"></i></span>
                    <span class="preview-title"><?= e(__('settings.temas.preview_title')) ?></span>
                </div>
                <div class="preview-row">
                    <span class="preview-bar"><span></span></span>
                    <span class="preview-amount">$1,250.00</span>
                </div>
                <div class="preview-actions">
                    <button type="button" class="btn btn-sm btn-primary"><?= e(__('settings.temas.preview_button')) ?></button>
                    <span class="preview-fab" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
                </div>
            </div>
        </div>
    </div>
</div>
