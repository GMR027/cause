<?php

$errors = $errors ?? [];
?>
<div class="onboarding-card surface-card text-center">
    <span class="tutorial-icon mx-auto" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
    <h1 class="h4 mb-1"><?= e(__('onboarding.primer_ingreso.heading')) ?></h1>
    <p class="text-secondary mb-4"><?= e(__('onboarding.primer_ingreso.subtitle')) ?></p>

    <?= partial('partials/errors', ['errors' => $errors]) ?>

    <form method="post" action="/onboarding/primer-ingreso" class="d-grid gap-2 d-sm-flex justify-content-center">
        <?= csrf_field() ?>
        <button type="submit" name="respuesta" value="si" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('onboarding.primer_ingreso.yes')) ?>
        </button>
        <button type="submit" name="respuesta" value="no" class="btn btn-outline-secondary">
            <?= e(__('onboarding.primer_ingreso.no')) ?>
        </button>
    </form>

    <p class="small text-secondary mt-3 mb-0"><?= e(__('onboarding.primer_ingreso.hint')) ?></p>
</div>
