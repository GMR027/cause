<?php

$recurrences = $recurrences ?? [];
$errors = $errors ?? [];
$current = (string) ($state['recurrencia'] ?? 'mensual');
?>
<div class="onboarding-card surface-card">
    <h1 class="h4 mb-1"><?= e(__('onboarding.recurrencia.heading')) ?></h1>
    <p class="text-secondary mb-4"><?= e(__('onboarding.recurrencia.subtitle')) ?></p>

    <?= partial('partials/errors', ['errors' => $errors]) ?>

    <form method="post" action="/onboarding/recurrencia">
        <?= csrf_field() ?>

        <div class="option-grid">
            <?php foreach ($recurrences as $recurrence): ?>
                <label class="option-card">
                    <input type="radio" name="recurrencia" value="<?= e((string) $recurrence) ?>"
                           <?= $current === $recurrence ? 'checked' : '' ?>>
                    <span class="option-body">
                        <span class="option-title"><?= e(__('onboarding.recurrencia.' . (string) $recurrence)) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="onboarding-actions">
            <button type="submit" class="btn btn-primary"><?= e(__('common.actions.continue')) ?></button>
        </div>
    </form>
</div>
