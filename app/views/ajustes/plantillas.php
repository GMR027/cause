<?php

$templates = $templates ?? [];
$tags = $tags ?? [];
$categories = $categories ?? [];
$subcategories = $subcategories ?? [];
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('expenses.templates.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('expenses.templates.subtitle')) ?></p>
    </div>
    <a href="/ajustes" class="btn btn-outline-secondary d-none d-xl-inline-flex"><?= e(__('common.actions.back')) ?></a>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-5">
        <div class="surface-card">
            <h2 class="h5 mb-1"><?= e(__('expenses.templates.create_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('expenses.templates.create_hint')) ?></p>

            <form method="post" action="/ajustes/plantillas">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label small" for="nombre"><?= e(__('expenses.templates.name')) ?></label>
                    <input type="text" class="form-control" id="nombre" name="nombre" maxlength="120" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small" for="monto"><?= e(__('expenses.templates.amount')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="monto" name="monto"
                               min="0.01" step="0.01" inputmode="decimal" required>
                    </div>
                </div>

                <div class="mb-3">
                    <?= partial('partials/currency-select', [
                        'name' => 'moneda',
                        'selected' => (is_string($_POST['moneda'] ?? null) ? $_POST['moneda'] : (string) ($currentUser['moneda_activa'] ?? 'MXN')),
                    ]) ?>
                </div>

                <div class="mb-3">
                    <label class="form-label small" for="parte_metodo"><?= e(__('expenses.templates.part')) ?></label>
                    <select class="form-select" id="parte_metodo" name="parte_metodo" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['nombre']) ?>"><?= e((string) $category['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small" for="subcategoria_id"><?= e(__('expenses.templates.subcategory')) ?></label>
                    <select class="form-select" id="subcategoria_id" name="subcategoria_id">
                        <option value=""><?= e(__('expenses.form.no_subcategory')) ?></option>
                        <?php foreach ($subcategories as $subcategory): ?>
                            <option value="<?= (int) $subcategory['id'] ?>"><?= e((string) $subcategory['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($tags !== []): ?>
                    <fieldset class="mb-3">
                        <legend class="form-label small"><?= e(__('expenses.templates.tags')) ?></legend>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($tags as $tag): ?>
                                <label class="form-check small">
                                    <input type="checkbox" class="form-check-input" name="tags[]"
                                           value="<?= (int) $tag['id'] ?>">
                                    <span class="form-check-label">#<?= e((string) $tag['nombre_normalizado']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary"><?= e(__('expenses.templates.create')) ?></button>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="surface-card">
            <h2 class="h5 mb-3"><?= e(__('expenses.templates.list_title')) ?></h2>

            <?php if ($templates === []): ?>
                <p class="text-secondary mb-0"><?= e(__('expenses.templates.empty')) ?></p>
            <?php else: ?>
                <div class="settings-list">
                    <?php foreach ($templates as $template): ?>
                        <div class="settings-row">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block"><?= e((string) $template['nombre']) ?></span>
                                <span class="small text-secondary">
                                    <?= e((string) $template['parte_metodo']) ?>
                                    <?php if (!empty($template['subcategoria'])): ?>
                                        · <?= e((string) $template['subcategoria']) ?>
                                    <?php endif; ?>
                                    · <?= e(__('expenses.templates.uses', [':count' => (string) (int) $template['usos']])) ?>
                                </span>
                                <?php if (!empty($template['tag_names'])): ?>
                                    <span class="d-flex flex-wrap gap-1 mt-1">
                                        <?php foreach ($template['tag_names'] as $tagName): ?>
                                            <span class="tag-chip tag-chip-sm">#<?= e($tagName) ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <span class="fw-semibold text-nowrap">
                                <?= money_html((int) $template['monto_centavos'], (string) $template['moneda']) ?>
                            </span>

                            <form method="post" action="/ajustes/plantillas/eliminar" class="m-0"
                                  data-confirm="<?= e(__('expenses.templates.confirm_delete')) ?>" data-confirm-danger>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $template['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?= e(__('expenses.templates.delete')) ?>">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
