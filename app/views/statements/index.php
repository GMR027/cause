<?php
$consent = (bool) ($consent ?? false);
$reauth = (bool) ($reauth ?? false);
$documents = $documents ?? [];
$usage = $usage ?? ['count' => 0, 'bytes' => 0];
$maxBytes = (int) ($maxBytes ?? 10485760);
$maxFiles = (int) ($maxFiles ?? 240);
$consentVersion = (string) ($consentVersion ?? '');
$errors = $errors ?? [];
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('statements.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('statements.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('statements.subtitle')) ?></p>
    </div>
    <a href="/ajustes/privacidad" class="btn btn-outline-secondary">
        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
        <?= e(__('nav.privacidad')) ?>
    </a>
</div>

<div class="alert alert-warning" role="alert">
    <h2 class="h6 mb-2"><?= e(__('statements.notice.title')) ?></h2>
    <p class="small mb-2"><?= e(__('statements.notice.body')) ?></p>
    <p class="small mb-0"><?= e(__('statements.notice.version', [':version' => $consentVersion])) ?></p>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$consent): ?>
    <div class="surface-card">
        <h2 class="h5 mb-2"><?= e(__('statements.consent.title')) ?></h2>
        <p class="small text-secondary mb-3"><?= e(__('statements.consent.hint')) ?></p>
        <form method="post" action="/estado-cuenta/consentimiento">
            <?= csrf_field() ?>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="statement-consent" name="acepto" value="1" required>
                <label class="form-check-label" for="statement-consent">
                    <?= e(__('statements.consent.checkbox')) ?>
                </label>
            </div>
            <button type="submit" class="btn btn-primary"><?= e(__('statements.consent.submit')) ?></button>
        </form>
    </div>
<?php elseif (!$reauth): ?>
    <div class="surface-card statement-auth-card">
        <h2 class="h5 mb-2"><?= e(__('statements.auth.title')) ?></h2>
        <p class="small text-secondary mb-3"><?= e(__('statements.auth.hint')) ?></p>
        <form method="post" action="/estado-cuenta/autenticar" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-12 col-md-8">
                <label class="form-label" for="statement-password"><?= e(__('common.fields.password')) ?></label>
                <input type="password" class="form-control" id="statement-password" name="password"
                       autocomplete="current-password" required autofocus>
            </div>
            <div class="col-12 col-md-4">
                <button type="submit" class="btn btn-primary w-100"><?= e(__('statements.auth.submit')) ?></button>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-7">
            <div class="surface-card h-100">
                <h2 class="h5 mb-2"><?= e(__('statements.upload.title')) ?></h2>
                <p class="small text-secondary mb-3">
                    <?= e(__('statements.upload.hint', [
                        ':max' => format_bytes($maxBytes),
                        ':files' => (string) $maxFiles,
                    ])) ?>
                </p>
                <form method="post" action="/estado-cuenta/subir" enctype="multipart/form-data" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label" for="statement-file"><?= e(__('statements.upload.file')) ?></label>
                        <input type="file" class="form-control" id="statement-file" name="archivo"
                               accept="application/pdf,.pdf" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="statement-type"><?= e(__('statements.upload.type')) ?></label>
                        <select class="form-select" id="statement-type" name="tipo">
                            <option value="banco"><?= e(__('statements.types.banco')) ?></option>
                            <option value="inversion"><?= e(__('statements.types.inversion')) ?></option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1" aria-hidden="true"></i>
                            <?= e(__('statements.upload.submit')) ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="surface-card h-100">
                <span class="small text-secondary d-block"><?= e(__('statements.usage.title')) ?></span>
                <p class="display-6 mb-1"><?= (int) $usage['count'] ?></p>
                <p class="small text-secondary mb-3">
                    <?= e(__('statements.usage.files', [':max' => (string) $maxFiles])) ?>
                </p>
                <p class="small mb-0">
                    <i class="bi bi-hdd me-1" aria-hidden="true"></i>
                    <?= e(format_bytes((int) $usage['bytes'])) ?>
                </p>
            </div>
        </div>
    </div>

    <div class="surface-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h5 mb-0"><?= e(__('statements.list.title')) ?></h2>
            <span class="badge text-bg-light border"><?= (int) $usage['count'] ?></span>
        </div>

        <?php if ($documents === []): ?>
            <p class="text-secondary mb-0"><?= e(__('statements.list.empty')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= e(__('statements.list.name')) ?></th>
                            <th><?= e(__('statements.list.size')) ?></th>
                            <th><?= e(__('statements.list.date')) ?></th>
                            <th><?= e(__('statements.list.type')) ?></th>
                            <th class="text-end"><?= e(__('common.admin.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $document): ?>
                            <tr>
                                <td class="text-break"><?= e((string) $document['nombre_original']) ?></td>
                                <td class="small text-secondary"><?= e(format_bytes((int) $document['tamano_bytes'])) ?></td>
                                <td class="small text-secondary"><?= e(format_datetime($document['fecha_subida'] ?? null)) ?></td>
                                <td>
                                    <span class="badge text-bg-light border">
                                        <?= e(__('statements.types.' . (string) $document['tipo'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <a href="/estado-cuenta/ver?id=<?= (int) $document['id'] ?>"
                                           class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                                            <?= e(__('common.actions.view')) ?>
                                        </a>
                                        <form method="post" action="/estado-cuenta/eliminar" class="m-0"
                                              data-confirm="<?= e(__('statements.list.confirm_delete')) ?>" data-confirm-danger>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $document['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <?= e(__('common.actions.delete')) ?>
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
<?php endif; ?>
