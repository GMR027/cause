<?php
$documents = $documents ?? [];
$errors = $errors ?? [];
$lang = current_lang();
?>
<div class="row justify-content-center">
    <div class="col-12 col-xl-9">
        <div class="page-heading">
            <div>
                <span class="badge phase-badge mb-2"><?= e(__('legal.badge')) ?></span>
                <h1 class="h3 mb-1"><?= e(__('legal.accept.title')) ?></h1>
                <p class="text-secondary mb-0"><?= e(__('legal.accept.subtitle')) ?></p>
            </div>
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

        <?php foreach ($documents as $document): ?>
            <section class="surface-card mb-3">
                <h2 class="h5 mb-1">
                    <?= e(\Cauce\Services\LegalService::title($document, $lang)) ?>
                </h2>
                <p class="small text-secondary mb-3">
                    <?= e(__('legal.version', [':version' => (string) $document['version']])) ?>
                </p>
                <div class="legal-body legal-body-scroll">
                    <?= nl2br(e(\Cauce\Services\LegalService::content($document, $lang))) ?>
                </div>
            </section>
        <?php endforeach; ?>

        <form method="post" action="/legal/aceptar" class="surface-card">
            <?= csrf_field() ?>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="legal-accept" name="acepto" value="1" required>
                <label class="form-check-label" for="legal-accept">
                    <?= e(__('legal.accept.checkbox')) ?>
                </label>
            </div>
            <button type="submit" class="btn btn-primary"><?= e(__('legal.accept.submit')) ?></button>
        </form>

        <form method="post" action="/logout" class="mt-3">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-link btn-sm text-secondary px-0">
                <?= e(__('common.actions.logout')) ?>
            </button>
        </form>
    </div>
</div>
