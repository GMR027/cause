<?php

$tags = $tags ?? [];
$count = count($tags);
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('expenses.tags.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('expenses.tags.subtitle')) ?></p>
    </div>
    <a href="/ajustes" class="btn btn-outline-secondary d-none d-xl-inline-flex"><?= e(__('common.actions.back')) ?></a>
</div>

<div class="surface-card mb-3">
    <h2 class="h5 mb-1"><?= e(__('expenses.tags.new_title')) ?></h2>
    <p class="small text-secondary mb-3"><?= e(__('expenses.tags.new_hint')) ?></p>
    <form method="post" action="/ajustes/tags" class="row g-2 align-items-end mb-4">
        <?= csrf_field() ?>
        <div class="col-12 col-md-8">
            <label class="form-label small" for="tag-name"><?= e(__('expenses.tags.name')) ?></label>
            <input type="text" class="form-control" id="tag-name" name="nombre" maxlength="80"
                   placeholder="<?= e(__('expenses.tags.name_placeholder')) ?>" required>
        </div>
        <div class="col-12 col-md-4">
            <button type="submit" class="btn btn-primary w-100"><?= e(__('expenses.tags.create')) ?></button>
        </div>
    </form>

    <?php if ($tags === []): ?>
        <p class="text-secondary mb-0"><?= e(__('expenses.tags.empty')) ?></p>
    <?php else: ?>
        <div class="settings-list">
            <?php foreach ($tags as $tag): ?>
                <div class="settings-row">
                    <div class="flex-grow-1">
                        <span class="fw-semibold">#<?= e((string) $tag['nombre_normalizado']) ?></span>
                        <span class="small text-secondary d-block">
                            <?= e(__('expenses.tags.uses', [':count' => (string) (int) $tag['usos']])) ?>
                        </span>
                    </div>

                    <form method="post" action="/ajustes/tags/renombrar" class="d-flex gap-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $tag['id'] ?>">
                        <input type="text" class="form-control form-control-sm" name="nombre"
                               value="<?= e((string) $tag['nombre_normalizado']) ?>" maxlength="80"
                               aria-label="<?= e(__('expenses.tags.rename')) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <?= e(__('expenses.tags.rename')) ?>
                        </button>
                    </form>

                    <form method="post" action="/ajustes/tags/eliminar" class="m-0"
                          data-confirm="<?= e(__('expenses.tags.confirm_delete')) ?>" data-confirm-danger>
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $tag['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?= e(__('expenses.tags.delete')) ?>">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($count >= 2): ?>
    <div class="surface-card">
        <h2 class="h5 mb-1"><?= e(__('expenses.tags.merge_title')) ?></h2>
        <p class="small text-secondary mb-3"><?= e(__('expenses.tags.merge_hint')) ?></p>

        <form method="post" action="/ajustes/tags/fusionar" class="row g-2 align-items-end">
            <?= csrf_field() ?>

            <div class="col-12 col-md-5">
                <label class="form-label small" for="source_id"><?= e(__('expenses.tags.merge_source')) ?></label>
                <select class="form-select" id="source_id" name="source_id" required>
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?= (int) $tag['id'] ?>">#<?= e((string) $tag['nombre_normalizado']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-5">
                <label class="form-label small" for="target_id"><?= e(__('expenses.tags.merge_target')) ?></label>
                <select class="form-select" id="target_id" name="target_id" required>
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?= (int) $tag['id'] ?>">#<?= e((string) $tag['nombre_normalizado']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary w-100"><?= e(__('expenses.tags.merge')) ?></button>
            </div>
        </form>
    </div>
<?php endif; ?>
