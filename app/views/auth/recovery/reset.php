<?php
$errors = $errors ?? [];
?>
<div class="card border-0 shadow-sm auth-card">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h3 fw-bold mb-2"><?= e(__('auth.reset.heading')) ?></h1>
        <p class="text-secondary mb-4"><?= e(__('auth.reset.subtitle')) ?></p>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="/recuperar/restablecer" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="password" class="form-label"><?= e(__('common.fields.new_password')) ?></label>
                <input type="password" class="form-control" id="password" name="password"
                       minlength="8" autocomplete="new-password" required autofocus>
                <div class="form-text"><?= e(__('common.validation.password')) ?></div>
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label"><?= e(__('common.fields.password_confirmation')) ?></label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
                       minlength="8" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100"><?= e(__('auth.reset.submit')) ?></button>
        </form>
    </div>
</div>
