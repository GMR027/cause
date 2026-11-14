<?php
$lessons = $lessons ?? [];
$editing = $editing ?? null;
$sections = $sections ?? [];
$isEditing = is_array($editing);
$editingId = $isEditing ? (int) $editing['id'] : 0;
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('lessons.admin.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('lessons.admin.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('lessons.admin.subtitle')) ?></p>
    </div>
</div>

<div class="surface-card mb-4">
    <h2 class="h5 mb-1">
        <?= e($isEditing ? __('lessons.admin.edit_title') : __('lessons.admin.create_title')) ?>
    </h2>
    <p class="small text-secondary mb-3"><?= e(__('lessons.admin.hint')) ?></p>

    <form method="post" action="/admin/contenido/guardar" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $editingId ?>">

        <div class="col-12 col-md-4">
            <label class="form-label" for="lesson-code"><?= e(__('lessons.admin.code')) ?></label>
            <input type="text" class="form-control" id="lesson-code" name="codigo" maxlength="40"
                   value="<?= e((string) ($editing['codigo'] ?? '')) ?>"
                   <?= $isEditing ? 'readonly' : 'required' ?>
                   pattern="[a-z0-9_]{3,40}">
            <div class="form-text"><?= e(__('lessons.admin.code_hint')) ?></div>
        </div>

        <div class="col-12 col-md-4">
            <label class="form-label" for="lesson-section"><?= e(__('lessons.admin.section')) ?></label>
            <select class="form-select" id="lesson-section" name="seccion" required>
                <?php foreach ($sections as $section): ?>
                    <option value="<?= e($section) ?>"
                            <?= (string) ($editing['seccion'] ?? '') === $section ? 'selected' : '' ?>>
                        <?= e(__('lessons.sections.' . $section)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label" for="lesson-order"><?= e(__('lessons.admin.order')) ?></label>
            <input type="number" class="form-control" id="lesson-order" name="orden" min="0" max="255"
                   value="<?= (int) ($editing['orden'] ?? 0) ?>">
        </div>

        <div class="col-6 col-md-2 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="lesson-published" name="publicada" value="1"
                       <?= !$isEditing || !empty($editing['publicada']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="lesson-published"><?= e(__('lessons.admin.published')) ?></label>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="lesson-title-es"><?= e(__('lessons.admin.title_es')) ?></label>
            <input type="text" class="form-control" id="lesson-title-es" name="titulo_es" maxlength="160"
                   value="<?= e((string) ($editing['titulo_es'] ?? '')) ?>" required>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="lesson-title-en"><?= e(__('lessons.admin.title_en')) ?></label>
            <input type="text" class="form-control" id="lesson-title-en" name="titulo_en" maxlength="160"
                   value="<?= e((string) ($editing['titulo_en'] ?? '')) ?>">
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="lesson-content-es"><?= e(__('lessons.admin.content_es')) ?></label>
            <textarea class="form-control" id="lesson-content-es" name="contenido_es" rows="8" required><?= e((string) ($editing['contenido_es'] ?? '')) ?></textarea>
            <div class="form-text"><?= e(__('lessons.admin.content_hint')) ?></div>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="lesson-content-en"><?= e(__('lessons.admin.content_en')) ?></label>
            <textarea class="form-control" id="lesson-content-en" name="contenido_en" rows="8"><?= e((string) ($editing['contenido_en'] ?? '')) ?></textarea>
            <div class="form-text"><?= e(__('lessons.admin.content_hint')) ?></div>
        </div>

        <div class="col-12 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">
                <?= e($isEditing ? __('common.actions.save') : __('lessons.admin.create')) ?>
            </button>
            <?php if ($isEditing): ?>
                <a href="/admin/contenido" class="btn btn-outline-secondary"><?= e(__('common.actions.cancel')) ?></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="surface-card">
    <h2 class="h5 mb-3"><?= e(__('lessons.admin.list_title')) ?></h2>

    <?php if ($lessons === []): ?>
        <p class="small text-secondary mb-0"><?= e(__('lessons.admin.empty')) ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= e(__('lessons.admin.code')) ?></th>
                        <th><?= e(__('lessons.admin.section')) ?></th>
                        <th><?= e(__('lessons.admin.title_es')) ?></th>
                        <th><?= e(__('lessons.admin.status')) ?></th>
                        <th class="text-end"><?= e(__('lessons.admin.order')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lessons as $lesson): ?>
                        <tr>
                            <td><code><?= e((string) $lesson['codigo']) ?></code></td>
                            <td class="small text-secondary">
                                <?= e(__('lessons.sections.' . (string) $lesson['seccion'])) ?>
                            </td>
                            <td><?= e((string) $lesson['titulo_es']) ?></td>
                            <td>
                                <span class="badge <?= !empty($lesson['publicada']) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= e(!empty($lesson['publicada']) ? __('lessons.admin.published') : __('lessons.admin.unpublished')) ?>
                                </span>
                            </td>
                            <td class="text-end"><?= (int) $lesson['orden'] ?></td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-2">
                                    <a href="/admin/contenido?editar=<?= (int) $lesson['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary">
                                        <?= e(__('common.actions.edit')) ?>
                                    </a>
                                    <form method="post" action="/admin/contenido/publicar" class="m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $lesson['id'] ?>">
                                        <input type="hidden" name="publicada" value="<?= !empty($lesson['publicada']) ? '0' : '1' ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <?= e(!empty($lesson['publicada']) ? __('lessons.admin.unpublish') : __('lessons.admin.publish')) ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
