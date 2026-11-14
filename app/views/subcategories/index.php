<?php
$grouped = $grouped ?? [];
$parts = $parts ?? [];
$allowedParts = $allowed_parts ?? $parts;
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('subcategories.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('subcategories.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('subcategories.subtitle')) ?></p>
    </div>
    <a href="/ajustes" class="btn btn-outline-secondary"><?= e(__('subcategories.back')) ?></a>
</div>

<div class="surface-card mb-4">
    <h2 class="h5 mb-1"><?= e(__('subcategories.new_title')) ?></h2>
    <p class="small text-secondary mb-3"><?= e(__('subcategories.new_hint')) ?></p>
    <form method="post" action="/subcategorias" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <div class="col-12 col-md-5">
            <label class="form-label small" for="subcategory-name"><?= e(__('subcategories.name')) ?></label>
            <input type="text" class="form-control" id="subcategory-name" name="nombre" maxlength="80"
                   placeholder="<?= e(__('subcategories.name_placeholder')) ?>" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small" for="subcategory-part"><?= e(__('subcategories.part')) ?></label>
            <select class="form-select" id="subcategory-part" name="parte" required>
                <?php foreach ($allowedParts as $part): ?>
                    <option value="<?= e($part) ?>"><?= e($part) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <button type="submit" class="btn btn-primary w-100"><?= e(__('subcategories.create')) ?></button>
        </div>
    </form>
</div>

<?php if ($grouped === []): ?>
    <div class="surface-card text-center py-4">
        <i class="bi bi-diagram-3 fs-2 text-secondary" aria-hidden="true"></i>
        <p class="mt-2 mb-0 text-secondary"><?= e(__('subcategories.empty')) ?></p>
    </div>
<?php else: ?>
    <?php foreach ($parts as $part): ?>
        <?php $items = $grouped[$part] ?? []; ?>
        <div class="surface-card mb-3">
            <h2 class="h5 mb-3"><?= e($part) ?></h2>
            <?php if ($items === []): ?>
                <p class="small text-secondary mb-0"><?= e(__('subcategories.empty')) ?></p>
            <?php else: ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($items as $item): ?>
                        <div class="border rounded-3 p-2 d-flex flex-wrap align-items-center gap-2">
                            <form method="post" action="/subcategorias/actualizar"
                                  class="row g-2 align-items-center flex-grow-1 m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <div class="col-12 col-md-5">
                                    <input type="text" class="form-control form-control-sm" name="nombre"
                                           value="<?= e((string) $item['nombre']) ?>" maxlength="80"
                                           aria-label="<?= e(__('subcategories.name')) ?>" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <select class="form-select form-select-sm" name="parte"
                                            aria-label="<?= e(__('subcategories.part')) ?>">
                                        <?php foreach ($parts as $option): ?>
                                            <option value="<?= e($option) ?>"
                                                    <?= ($option === (string) ($item['parte_metodo_actual'] ?? $item['parte_metodo_default'])) ? 'selected' : '' ?>>
                                                <?= e($option) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2 small text-secondary">
                                    <?= e(__('subcategories.uses', [':count' => (string) (int) ($item['usos'] ?? 0)])) ?>
                                </div>
                                <div class="col-6 col-md-1 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        <?= e(__('subcategories.save')) ?>
                                    </button>
                                </div>
                            </form>

                            <form method="post" action="/subcategorias/eliminar" class="m-0"
                                  onsubmit="return confirm('<?= e(__('subcategories.confirm_delete')) ?>')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <?= e(__('subcategories.delete')) ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
