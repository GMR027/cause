<?php
$documents = $documents ?? [];
$current = $current ?? [];
$counts = $counts ?? [];
$errors = $errors ?? [];
$types = ['privacidad' => __('legal.titles.privacidad'), 'terminos' => __('legal.titles.terminos'), 'aviso' => __('legal.titles.aviso')];
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('admin.legal.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('admin.legal.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('admin.legal.subtitle')) ?></p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($types as $type => $label): ?>
        <?php $document = $current[$type] ?? null; ?>
        <div class="col-12 col-md-4">
            <div class="surface-card h-100">
                <span class="small text-secondary d-block"><?= e($label) ?></span>
                <?php if ($document !== null): ?>
                    <p class="h5 mb-1">v<?= e((string) $document['version']) ?></p>
                    <p class="small text-secondary mb-1">
                        <?= e(format_datetime($document['published_at'] ?? null)) ?>
                    </p>
                    <p class="small mb-0">
                        <?= e(__('admin.legal.accepted_count', [':count' => (string) ($counts[$type] ?? 0)])) ?>
                    </p>
                <?php else: ?>
                    <p class="small text-secondary mb-0"><?= e(__('admin.legal.no_current')) ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="surface-card mb-4">
    <h2 class="h5 mb-3"><?= e(__('admin.legal.publish_title')) ?></h2>
    <p class="small text-secondary mb-3"><?= e(__('admin.legal.publish_hint')) ?></p>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/legal/publicar" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-12 col-md-4">
            <label class="form-label" for="legal-type"><?= e(__('admin.legal.type')) ?></label>
            <select class="form-select" id="legal-type" name="tipo">
                <?php foreach ($types as $type => $label): ?>
                    <option value="<?= e($type) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="legal-version"><?= e(__('admin.legal.version')) ?></label>
            <input type="text" class="form-control" id="legal-version" name="version" placeholder="1.1" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="legal-title-es"><?= e(__('admin.legal.title_es')) ?></label>
            <input type="text" class="form-control" id="legal-title-es" name="titulo_es" maxlength="160" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="legal-title-en"><?= e(__('admin.legal.title_en')) ?></label>
            <input type="text" class="form-control" id="legal-title-en" name="titulo_en" maxlength="160" required>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="legal-content-es"><?= e(__('admin.legal.content_es')) ?></label>
            <textarea class="form-control" id="legal-content-es" name="contenido_es" rows="8" required></textarea>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="legal-content-en"><?= e(__('admin.legal.content_en')) ?></label>
            <textarea class="form-control" id="legal-content-en" name="contenido_en" rows="8" required></textarea>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary"><?= e(__('admin.legal.publish')) ?></button>
        </div>
    </form>
</div>

<div class="surface-card">
    <h2 class="h5 mb-3"><?= e(__('admin.legal.history')) ?></h2>
    <?php if ($documents === []): ?>
        <p class="small text-secondary mb-0"><?= e(__('admin.legal.no_documents')) ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= e(__('admin.legal.type')) ?></th>
                        <th><?= e(__('admin.legal.version')) ?></th>
                        <th><?= e(__('admin.legal.status')) ?></th>
                        <th><?= e(__('admin.legal.published_at')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $document): ?>
                        <tr>
                            <td><?= e($types[(string) $document['tipo']] ?? (string) $document['tipo']) ?></td>
                            <td>v<?= e((string) $document['version']) ?></td>
                            <td>
                                <span class="badge <?= !empty($document['vigente']) ? 'text-bg-success' : 'text-bg-light border' ?>">
                                    <?= e(!empty($document['vigente']) ? __('admin.legal.current') : __('admin.legal.archived')) ?>
                                </span>
                            </td>
                            <td class="small text-secondary"><?= e(format_datetime($document['published_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
