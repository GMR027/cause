<?php
$old = $old ?? ['alias' => '', 'email' => '', 'idioma' => current_lang(), 'rol' => 'user'];
$errors = $errors ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= e(__('common.admin.new_user')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('common.admin.welcome_hint')) ?></p>
    </div>
    <a href="/admin/usuarios" class="btn btn-outline-secondary"><?= e(__('common.actions.back')) ?></a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="/admin/usuarios" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="alias" class="form-label"><?= e(__('common.fields.alias')) ?></label>
                    <input type="text" class="form-control" id="alias" name="alias"
                           value="<?= e($old['alias'] ?? '') ?>" minlength="3" maxlength="30"
                           pattern="[A-Za-z0-9_-]+" required>
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label"><?= e(__('common.fields.email')) ?></label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= e($old['email'] ?? '') ?>" maxlength="190" required>
                </div>

                <div class="col-md-6">
                    <label for="password" class="form-label"><?= e(__('common.fields.password')) ?></label>
                    <input type="password" class="form-control" id="password" name="password"
                           minlength="8" autocomplete="new-password" required>
                    <div class="form-text"><?= e(__('common.validation.password')) ?></div>
                </div>

                <div class="col-md-3">
                    <label for="idioma" class="form-label"><?= e(__('common.fields.idioma')) ?></label>
                    <select class="form-select" id="idioma" name="idioma">
                        <option value="es" <?= ($old['idioma'] ?? 'es') === 'es' ? 'selected' : '' ?>>Español</option>
                        <option value="en" <?= ($old['idioma'] ?? 'es') === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="rol" class="form-label"><?= e(__('common.fields.rol')) ?></label>
                    <select class="form-select" id="rol" name="rol">
                        <option value="user" <?= ($old['rol'] ?? 'user') === 'user' ? 'selected' : '' ?>>
                            <?= e(__('common.roles.user')) ?>
                        </option>
                        <option value="admin" <?= ($old['rol'] ?? 'user') === 'admin' ? 'selected' : '' ?>>
                            <?= e(__('common.roles.admin')) ?>
                        </option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-4"><?= e(__('common.admin.create_user')) ?></button>
        </form>
    </div>
</div>
