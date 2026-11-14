<?php
$version = (string) ($version ?? '0.0.0');
$principles = lang_array('about.principles');
$methods = lang_array('about.methods');
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('about.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('about.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('about.subtitle')) ?></p>
    </div>
    <a href="/ajustes" class="btn btn-outline-secondary"><?= e(__('about.back')) ?></a>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-7">
        <div class="surface-card h-100">
            <h2 class="h5 mb-2"><?= e(__('about.what_title')) ?></h2>
            <p class="text-secondary mb-3"><?= e(__('about.what_body')) ?></p>
            <div class="alert alert-secondary py-2 small mb-0" role="status">
                <strong class="d-block mb-1"><?= e(__('about.not_title')) ?></strong>
                <?= e(__('about.not_body')) ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="surface-card h-100">
            <h2 class="h5 mb-2"><?= e(__('about.status_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('about.status_body')) ?></p>
            <span class="badge text-bg-primary"><?= e(__('about.version', [':version' => $version])) ?></span>
            <hr class="my-3">
            <h3 class="h6 mb-1"><?= e(__('about.roadmap_title')) ?></h3>
            <p class="small text-secondary mb-0"><?= e(__('about.roadmap_body')) ?></p>
        </div>
    </div>
</div>

<div class="surface-card mb-4">
    <h2 class="h5 mb-3"><?= e(__('about.how_title')) ?></h2>
    <div class="row g-3">
        <?php foreach ([1, 2, 3] as $step): ?>
            <div class="col-12 col-md-4">
                <div class="border rounded-3 p-3 h-100">
                    <span class="badge text-bg-light border mb-2"><?= $step ?></span>
                    <h3 class="h6 mb-1"><?= e(__('about.how_' . $step . '_title')) ?></h3>
                    <p class="small text-secondary mb-0"><?= e(__('about.how_' . $step . '_body')) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-lg-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('about.principles_title')) ?></h2>
            <ul class="mb-0 ps-3 small text-secondary">
                <?php foreach ($principles as $principle): ?>
                    <li class="mb-2"><?= e((string) $principle) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-2"><?= e(__('about.methods_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('about.methods_body')) ?></p>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($methods as $method): ?>
                    <span class="badge text-bg-light border"><?= e((string) $method) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="surface-card h-100">
            <h2 class="h5 mb-2"><?= e(__('about.support_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('about.support_body')) ?></p>
            <a href="/soporte" class="btn btn-primary"><?= e(__('about.support_cta')) ?></a>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="surface-card h-100">
            <h2 class="h5 mb-2"><?= e(__('about.legal_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('about.legal_body')) ?></p>
            <div class="d-flex flex-wrap gap-2">
                <a href="/privacidad" class="btn btn-sm btn-outline-secondary"><?= e(__('about.privacy')) ?></a>
                <a href="/terminos" class="btn btn-sm btn-outline-secondary"><?= e(__('about.terms')) ?></a>
                <a href="/aviso-legal" class="btn btn-sm btn-outline-secondary"><?= e(__('about.notice')) ?></a>
            </div>
        </div>
    </div>
</div>
