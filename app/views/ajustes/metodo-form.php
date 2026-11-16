<?php

$method = $method ?? null;
$nombre = (string) ($nombre ?? '');
$rows = $rows ?? [];
$activate = (bool) ($activate ?? true);
$isActive = (bool) ($isActive ?? false);
$types = $types ?? [];
$maxRows = (int) ($maxRows ?? 10);
$errors = $errors ?? [];
$isNew = $method === null;

$typeSelect = static function (string $name, string $selected, string $id) use ($types): string {
    $html = '<select class="form-select" name="' . e($name) . '" id="' . e($id) . '" data-custom-type>';

    foreach ($types as $type) {
        $html .= '<option value="' . e((string) $type) . '"' . ($selected === $type ? ' selected' : '') . '>'
            . e(ucfirst(__('income.types_category.' . (string) $type))) . '</option>';
    }

    return $html . '</select>';
};
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__($isNew ? 'settings.metodo.new_title' : 'settings.metodo.edit_title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('settings.metodo.form_subtitle')) ?></p>
    </div>
    <a href="/ajustes/ingreso" class="btn btn-outline-secondary d-none d-xl-inline-flex"><?= e(__('common.actions.back')) ?></a>
</div>

<?= partial('partials/errors', ['errors' => $errors]) ?>

<form method="post" action="/ajustes/ingreso/metodos/guardar" class="surface-card" data-custom-method-form data-max-rows="<?= $maxRows ?>">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?>
        <input type="hidden" name="id" value="<?= (int) $method['id'] ?>">
    <?php endif; ?>

    <div class="mb-4">
        <label class="form-label" for="metodo_nombre"><?= e(__('settings.metodo.name')) ?></label>
        <input type="text" class="form-control" id="metodo_nombre" name="nombre" maxlength="80" required
               value="<?= e($nombre) ?>" placeholder="<?= e(__('settings.metodo.name_placeholder')) ?>">
    </div>

    <h2 class="h6 mb-1"><?= e(__('settings.metodo.categories')) ?></h2>
    <p class="small text-secondary mb-3"><?= e(__('settings.metodo.categories_hint')) ?></p>

    <div class="custom-method-rows" data-custom-rows>
        <?php foreach (array_values($rows) as $index => $row): ?>
            <div class="custom-method-row" data-custom-row>
                <input type="hidden" name="categorias[<?= $index ?>][id]" value="<?= $row['id'] !== null ? (int) $row['id'] : '' ?>" data-custom-field="id">
                <div class="custom-method-name">
                    <label class="form-label small mb-1" for="cat_nombre_<?= $index ?>"><?= e(__('settings.metodo.category_name')) ?></label>
                    <input type="text" class="form-control" id="cat_nombre_<?= $index ?>" name="categorias[<?= $index ?>][nombre]"
                           maxlength="60" value="<?= e((string) $row['nombre']) ?>" data-custom-field="nombre">
                </div>
                <div class="custom-method-type">
                    <label class="form-label small mb-1" for="cat_tipo_<?= $index ?>"><?= e(__('settings.metodo.category_type')) ?></label>
                    <?= $typeSelect('categorias[' . $index . '][tipo]', (string) $row['tipo'], 'cat_tipo_' . $index) ?>
                </div>
                <div class="custom-method-percent">
                    <label class="form-label small mb-1" for="cat_pct_<?= $index ?>"><?= e(__('settings.metodo.category_percent')) ?></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="cat_pct_<?= $index ?>" name="categorias[<?= $index ?>][porcentaje]"
                               min="0" max="100" step="1" value="<?= e((string) $row['porcentaje']) ?>" data-custom-field="porcentaje" data-custom-percent>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-danger custom-method-remove" data-custom-remove
                        aria-label="<?= e(__('settings.metodo.remove_row')) ?>" title="<?= e(__('settings.metodo.remove_row')) ?>">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <template data-custom-template>
        <div class="custom-method-row" data-custom-row>
            <input type="hidden" name="categorias[__INDEX__][id]" value="" data-custom-field="id">
            <div class="custom-method-name">
                <label class="form-label small mb-1" for="cat_nombre___INDEX__"><?= e(__('settings.metodo.category_name')) ?></label>
                <input type="text" class="form-control" id="cat_nombre___INDEX__" name="categorias[__INDEX__][nombre]" maxlength="60" value="" data-custom-field="nombre">
            </div>
            <div class="custom-method-type">
                <label class="form-label small mb-1" for="cat_tipo___INDEX__"><?= e(__('settings.metodo.category_type')) ?></label>
                <?= $typeSelect('categorias[__INDEX__][tipo]', 'controlada', 'cat_tipo___INDEX__') ?>
            </div>
            <div class="custom-method-percent">
                <label class="form-label small mb-1" for="cat_pct___INDEX__"><?= e(__('settings.metodo.category_percent')) ?></label>
                <div class="input-group">
                    <input type="number" class="form-control" id="cat_pct___INDEX__" name="categorias[__INDEX__][porcentaje]" min="0" max="100" step="1" value="" data-custom-field="porcentaje" data-custom-percent>
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <button type="button" class="btn btn-outline-danger custom-method-remove" data-custom-remove
                    aria-label="<?= e(__('settings.metodo.remove_row')) ?>" title="<?= e(__('settings.metodo.remove_row')) ?>">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
    </template>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <button type="button" class="btn btn-outline-secondary" data-custom-add>
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('settings.metodo.add_row')) ?>
        </button>
        <span class="small text-secondary">
            <?= e(__('settings.ingreso.extra_total')) ?>:
            <span class="fw-semibold" data-custom-total>0</span>%
        </span>
    </div>

    <p class="small text-secondary mt-3 mb-0"><?= e(__('settings.metodo.types_hint')) ?></p>

    <hr class="my-4">

    <?php if ($isActive): ?>
        <p class="small text-secondary"><?= e(__('settings.metodo.active_note')) ?></p>
    <?php else: ?>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="activar" value="1" id="activar" <?= $activate ? 'checked' : '' ?>>
            <label class="form-check-label" for="activar"><?= e(__('settings.metodo.activate')) ?></label>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary"><?= e(__('common.actions.save')) ?></button>
        <a href="/ajustes/ingreso" class="btn btn-outline-secondary"><?= e(__('common.actions.cancel')) ?></a>
    </div>
</form>
