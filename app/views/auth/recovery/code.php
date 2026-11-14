<?php
$old = $old ?? ['email' => ''];
$errors = $errors ?? [];
?>
<div class="card border-0 shadow-sm auth-card">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h3 fw-bold mb-2"><?= e(__('auth.recovery.code_heading')) ?></h1>
        <p class="text-secondary mb-4"><?= e(__('auth.recovery.code_hint')) ?></p>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="/recuperar/codigo" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="email" class="form-label"><?= e(__('common.fields.email')) ?></label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= e($old['email'] ?? '') ?>" autocomplete="email" required autofocus>
            </div>

            <div class="mb-3">
                <label for="code" class="form-label"><?= e(__('common.fields.code')) ?></label>
                <input type="text" class="form-control text-uppercase" id="code" name="code"
                       placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="one-time-code" required>
            </div>

            <button type="submit" class="btn btn-primary w-100"><?= e(__('auth.recovery.submit_code')) ?></button>
        </form>

        <div class="mt-3 small">
            <a href="/recuperar"><?= e(__('common.actions.back')) ?></a>
        </div>
    </div>
</div>
