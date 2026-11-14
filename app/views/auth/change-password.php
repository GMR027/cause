<?php
$errors = $errors ?? [];
?>
<div class="card border-0 shadow-sm auth-card">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h4 fw-bold mb-2"><?= e(__('auth.change_password.heading')) ?></h1>
        <p class="text-secondary mb-4"><?= e(__('auth.change_password.subtitle')) ?></p>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="/ajustes/seguridad/cambiar-password" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="current_password" class="form-label"><?= e(__('common.fields.current_password')) ?></label>
                <input type="password" class="form-control" id="current_password" name="current_password"
                       autocomplete="current-password" required autofocus>
            </div>

            <div class="mb-3">
                <label for="new_password" class="form-label"><?= e(__('auth.change_password.new')) ?></label>
                <input type="password" class="form-control" id="new_password" name="new_password"
                       autocomplete="new-password" required>
                <div class="form-text"><?= e(__('auth.security.password_hint')) ?></div>
            </div>

            <div class="mb-3">
                <label for="new_password_confirmation" class="form-label">
                    <?= e(__('auth.change_password.confirm')) ?>
                </label>
                <input type="password" class="form-control" id="new_password_confirmation"
                       name="new_password_confirmation" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100"><?= e(__('auth.change_password.submit')) ?></button>
        </form>
    </div>
</div>
