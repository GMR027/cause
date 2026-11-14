<?php
$old = $old ?? ['alias' => '', 'email' => '', 'idioma' => current_lang(), 'questions' => []];
$errors = $errors ?? [];
$questions = is_array($old['questions'] ?? null) ? array_values($old['questions']) : [];
$defaults = lang_array('auth.register.default_questions');
?>
<div class="card border-0 shadow-sm auth-card">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h3 fw-bold mb-2"><?= e(__('auth.register.heading')) ?></h1>
        <p class="text-secondary mb-4"><?= e(__('auth.register.subtitle')) ?></p>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="/registro" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="alias" class="form-label"><?= e(__('common.fields.alias')) ?></label>
                    <input type="text" class="form-control" id="alias" name="alias"
                           value="<?= e($old['alias'] ?? '') ?>" minlength="3" maxlength="30"
                           pattern="[A-Za-z0-9_-]+" autocomplete="username" required>
                    <div class="form-text">3-30: <?= e(__('common.fields.alias')) ?> · A-Z 0-9 _ -</div>
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label"><?= e(__('common.fields.email')) ?></label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= e($old['email'] ?? '') ?>" maxlength="190" autocomplete="email" required>
                </div>

                <div class="col-md-6">
                    <label for="password" class="form-label"><?= e(__('common.fields.password')) ?></label>
                    <input type="password" class="form-control" id="password" name="password"
                           minlength="8" autocomplete="new-password" required>
                    <div class="form-text"><?= e(__('common.validation.password')) ?></div>
                </div>

                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label"><?= e(__('common.fields.password_confirmation')) ?></label>
                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
                           minlength="8" autocomplete="new-password" required>
                </div>

                <div class="col-md-6">
                    <label for="idioma" class="form-label"><?= e(__('common.fields.idioma')) ?></label>
                    <select class="form-select" id="idioma" name="idioma">
                        <option value="es" <?= ($old['idioma'] ?? 'es') === 'es' ? 'selected' : '' ?>>Español</option>
                        <option value="en" <?= ($old['idioma'] ?? 'es') === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <h2 class="h5 mb-1"><?= e(__('auth.register.questions_title')) ?></h2>
            <p class="text-secondary small mb-3"><?= e(__('auth.register.questions_hint')) ?></p>

            <?php for ($i = 0; $i < 3; $i++): ?>
                <?php
                $question = $questions[$i] ?? [];
                $questionText = (string) ($question['pregunta'] ?? $defaults[$i] ?? '');
                $answerText = (string) ($question['respuesta'] ?? '');
                ?>
                <div class="row g-2 mb-2 align-items-end">
                    <div class="col-md-7">
                        <label class="form-label small" for="question_<?= $i ?>">
                            <?= e(__('auth.fields.question', [':number' => (string) ($i + 1)])) ?>
                        </label>
                        <input type="text" class="form-control" id="question_<?= $i ?>"
                               name="questions[<?= $i ?>][pregunta]"
                               value="<?= e($questionText) ?>" maxlength="255" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small" for="answer_<?= $i ?>">
                            <?= e(__('auth.fields.answer', [':number' => (string) ($i + 1)])) ?>
                        </label>
                        <input type="text" class="form-control" id="answer_<?= $i ?>"
                               name="questions[<?= $i ?>][respuesta]"
                               value="<?= e($answerText) ?>" maxlength="255" required autocomplete="off">
                    </div>
                </div>
            <?php endfor; ?>

            <button type="submit" class="btn btn-primary w-100 mt-3"><?= e(__('auth.register.submit')) ?></button>
        </form>

        <div class="mt-4 small text-secondary">
            <?= e(__('auth.register.have_account')) ?>
            <a href="/login"><?= e(__('auth.register.login')) ?></a>
        </div>
    </div>
</div>
