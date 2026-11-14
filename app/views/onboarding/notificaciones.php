<?php

$errors = $errors ?? [];
?>
<div class="onboarding-card surface-card text-center">
    <span class="tutorial-icon mx-auto" aria-hidden="true"><i class="bi bi-bell"></i></span>
    <h1 class="h4 mb-1"><?= e(__('onboarding.notificaciones.heading')) ?></h1>
    <p class="text-secondary mb-4"><?= e(__('onboarding.notificaciones.subtitle')) ?></p>

    <?= partial('partials/errors', ['errors' => $errors]) ?>

    <ul class="notify-list text-start mx-auto">
        <li><i class="bi bi-check2-circle" aria-hidden="true"></i><?= e(__('onboarding.notificaciones.reason_1')) ?></li>
        <li><i class="bi bi-check2-circle" aria-hidden="true"></i><?= e(__('onboarding.notificaciones.reason_2')) ?></li>
        <li><i class="bi bi-check2-circle" aria-hidden="true"></i><?= e(__('onboarding.notificaciones.reason_3')) ?></li>
    </ul>

    <form method="post" action="/onboarding/notificaciones" class="d-grid gap-2 mt-4" data-notify-form>
        <?= csrf_field() ?>
        <button type="submit" name="respuesta" value="activadas" class="btn btn-primary" data-action="request-notifications"
                data-push-enabled="<?= !empty($push_enabled) ? '1' : '0' ?>"
                data-push-key="<?= e((string) ($push_public_key ?? '')) ?>">
            <?= e(__('onboarding.notificaciones.activate')) ?>
        </button>
        <button type="submit" name="respuesta" value="ahora_no" class="btn btn-outline-secondary">
            <?= e(__('onboarding.notificaciones.not_now')) ?>
        </button>
    </form>

    <p class="small text-secondary mt-3 mb-0" data-notify-status role="status" aria-live="polite">
        <?= e(__('onboarding.notificaciones.hint')) ?>
    </p>
</div>
