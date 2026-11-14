<?php
$hasPin = $hasPin ?? false;
$errors = $errors ?? [];
?>
<div class="card border-0 shadow-sm auth-card">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h4 fw-bold mb-2"><?= e(__('auth.unlock.heading')) ?></h1>
        <p class="text-secondary mb-4"><?= e(__('auth.unlock.subtitle')) ?></p>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="/desbloquear" novalidate>
            <?= csrf_field() ?>

            <?php if ($hasPin): ?>
                <div class="mb-3">
                    <label for="pin" class="form-label"><?= e(__('auth.unlock.pin')) ?></label>
                    <input type="password" class="form-control form-control-lg text-center"
                           id="pin" name="pin" inputmode="numeric" pattern="[0-9]*"
                           maxlength="<?= (int) config('security.pin.length', 6) ?>"
                           autocomplete="off" autofocus>
                    <div class="form-text"><?= e(__('auth.unlock.pin_hint')) ?></div>
                </div>

                <details class="mb-3">
                    <summary class="small text-secondary"><?= e(__('auth.unlock.use_password')) ?></summary>
                    <div class="mt-2">
                        <label for="password" class="form-label"><?= e(__('common.fields.password')) ?></label>
                        <input type="password" class="form-control" id="password" name="password"
                               autocomplete="current-password">
                    </div>
                </details>
            <?php else: ?>
                <div class="mb-3">
                    <label for="password" class="form-label"><?= e(__('common.fields.password')) ?></label>
                    <input type="password" class="form-control" id="password" name="password"
                           autocomplete="current-password" required autofocus>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary w-100"><?= e(__('auth.unlock.submit')) ?></button>
        </form>

        <form method="post" action="/logout" class="mt-3 text-center">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-link btn-sm text-secondary">
                <?= e(__('common.actions.logout')) ?>
            </button>
        </form>
    </div>
</div>
