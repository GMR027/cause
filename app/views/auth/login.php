<?php
$old = $old ?? ['email' => ''];
$errors = $errors ?? [];
$next = $next ?? '';
?>
<div class="card border-0 shadow-sm auth-card">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h3 fw-bold mb-2"><?= e(__('auth.login.heading')) ?></h1>
        <p class="text-secondary mb-4"><?= e(__('auth.login.subtitle')) ?></p>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="/login" novalidate>
            <?= csrf_field() ?>
            <?php if ($next !== ''): ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="email" class="form-label"><?= e(__('common.fields.email')) ?></label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= e($old['email'] ?? '') ?>" autocomplete="email" required autofocus>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label"><?= e(__('common.fields.password')) ?></label>
                <input type="password" class="form-control" id="password" name="password"
                       autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100"><?= e(__('auth.login.submit')) ?></button>
        </form>

        <div class="d-flex flex-column gap-1 mt-4 small">
            <a href="/recuperar"><?= e(__('auth.login.forgot')) ?></a>
            <span class="text-secondary">
                <?= e(__('auth.login.no_account')) ?>
                <a href="/registro"><?= e(__('auth.login.register')) ?></a>
            </span>
        </div>
    </div>
</div>
