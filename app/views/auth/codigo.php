<?php
$code = $code ?? '';
?>
<div class="card border-0 shadow-sm auth-card">
    <div class="card-body p-4 p-lg-5 text-center">
        <h1 class="h3 fw-bold mb-2"><?= e(__('auth.code.heading')) ?></h1>
        <p class="text-secondary mb-4"><?= e(__('auth.code.warning')) ?></p>

        <div class="alert alert-warning text-start" role="alert">
            <strong><?= e(__('auth.code.label')) ?></strong>
            <div class="recovery-code mt-2" id="recovery-code"><?= e($code) ?></div>
            <div class="small mt-2"><?= e(__('auth.code.copy_hint')) ?></div>
        </div>

        <button type="button" class="btn btn-outline-secondary btn-sm mb-4"
                data-copy-target="#recovery-code"><?= e(__('common.actions.copy')) ?></button>

        <form method="post" action="/registro/codigo">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('auth.code.continue')) ?></button>
        </form>
    </div>
</div>
