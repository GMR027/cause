<?php
$questions = $questions ?? [];
$locked = (bool) ($locked ?? false);
$minutes = (int) ($minutes ?? 0);
?>
<div class="card border-0 shadow-sm auth-card">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h3 fw-bold mb-2"><?= e(__('auth.recovery.questions_heading')) ?></h1>
        <p class="text-secondary mb-1"><?= e(__('auth.recovery.questions_hint')) ?></p>
        <p class="small text-secondary mb-4"><?= e($email ?? '') ?></p>

        <?php if ($locked): ?>
            <div class="alert alert-danger" role="alert">
                <?= e(__('auth.recovery.locked', [':minutes' => (string) $minutes])) ?>
            </div>
            <a href="/recuperar" class="btn btn-outline-secondary w-100"><?= e(__('common.actions.back')) ?></a>
        <?php else: ?>
            <form method="post" action="/recuperar/preguntas/responder" novalidate>
                <?= csrf_field() ?>

                <?php foreach ($questions as $index => $question): ?>
                    <div class="mb-3">
                        <label class="form-label" for="answer_<?= (int) $index ?>">
                            <?= e((string) $question['pregunta']) ?>
                        </label>
                        <input type="text" class="form-control" id="answer_<?= (int) $index ?>"
                               name="answers[<?= (int) $index ?>]" required autocomplete="off">
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary w-100"><?= e(__('auth.recovery.submit_questions')) ?></button>
            </form>

            <div class="mt-3 small">
                <a href="/recuperar"><?= e(__('common.actions.back')) ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>
