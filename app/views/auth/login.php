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

            <?php $method = (string) ($old['metodo'] ?? 'password') === 'pin' ? 'pin' : 'password'; ?>
            <fieldset class="mb-3" data-login-method>
                <legend class="visually-hidden"><?= e(__('auth.login.method')) ?></legend>
                <div class="btn-group w-100 login-method" role="group">
                    <input type="radio" class="btn-check" name="metodo" id="login-method-password" value="password"
                           data-no-help <?= $method === 'password' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary" for="login-method-password">
                        <i class="bi bi-key me-1" aria-hidden="true"></i><?= e(__('common.fields.password')) ?>
                    </label>
                    <input type="radio" class="btn-check" name="metodo" id="login-method-pin" value="pin"
                           data-no-help <?= $method === 'pin' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary" for="login-method-pin">
                        <i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i><?= e(__('auth.login.pin')) ?>
                    </label>
                </div>
            </fieldset>

            <div class="mb-3" data-login-panel="password" <?= $method === 'pin' ? 'hidden' : '' ?>>
                <label for="password" class="form-label"><?= e(__('common.fields.password')) ?></label>
                <input type="password" class="form-control" id="password" name="password"
                       autocomplete="current-password" <?= $method === 'pin' ? 'disabled' : 'required' ?>>
            </div>

            <div class="mb-3" data-login-panel="pin" <?= $method === 'pin' ? '' : 'hidden' ?>>
                <label for="login-pin" class="form-label"><?= e(__('auth.login.pin')) ?></label>
                <input type="password" class="form-control text-center login-pin-input" id="login-pin" name="pin"
                       inputmode="numeric" pattern="[0-9]*" maxlength="<?= (int) config('security.pin.length', 6) ?>"
                       autocomplete="off" <?= $method === 'pin' ? 'required' : 'disabled' ?>>
                <div class="form-text"><?= e(__('auth.login.pin_hint')) ?></div>
            </div>

            <button type="submit" class="btn btn-primary w-100"><?= e(__('auth.login.submit')) ?></button>
        </form>

        <?= partial('partials/passkey-button', [
            'action' => '/login/huella',
            'options' => '/login/huella/opciones',
            'label' => __('auth.passkeys.login'),
            'next' => $next,
        ]) ?>

        <div class="d-flex flex-column gap-1 mt-4 small">
            <a href="/recuperar"><?= e(__('auth.login.forgot')) ?></a>
            <span class="text-secondary">
                <?= e(__('auth.login.no_account')) ?>
                <a href="/registro"><?= e(__('auth.login.register')) ?></a>
            </span>
        </div>
    </div>
</div>
