<?php
$errors = $errors ?? [];
$questions = $questions ?? [];
$sessionCount = (int) ($sessionCount ?? 0);
$sessions = $sessions ?? [];
$hasPin = $hasPin ?? false;
$pinLockedUntil = $pinLockedUntil ?? null;
$pinLength = (int) ($pinLength ?? 6);
$passkeys = $passkeys ?? [];
$pinLogin = $pinLogin ?? false;
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1"><?= e(__('auth.security.heading')) ?></h1>
                <p class="text-secondary mb-4"><?= e(__('auth.security.subtitle')) ?></p>

                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <h2 class="h5 mb-1"><?= e(__('auth.security.password_title')) ?></h2>
                <p class="text-secondary small mb-3"><?= e(__('auth.security.password_hint')) ?></p>
                <a href="/ajustes/seguridad/cambiar-password" class="btn btn-sm btn-outline-secondary mb-4">
                    <?= e(__('auth.security.change_password')) ?>
                </a>

                <hr class="my-4">

                <h2 class="h5 mb-1"><?= e(__('auth.security.pin_title')) ?></h2>
                <p class="text-secondary small mb-3"><?= e(__('auth.security.pin_hint')) ?></p>

                <?php if ($pinLockedUntil !== null): ?>
                    <div class="alert alert-warning py-2 small" role="status">
                        <?= e(__('auth.security.pin_locked', [
                            ':minutes' => (string) \Cauce\Services\PinService::remainingLockMinutes($pinLockedUntil),
                        ])) ?>
                    </div>
                <?php endif; ?>

                <?php if ($hasPin): ?>
                    <p class="small mb-3">
                        <i class="bi bi-shield-check me-1 text-success" aria-hidden="true"></i>
                        <?= e(__('auth.security.pin_active')) ?>
                    </p>
                    <form method="post" action="/ajustes/seguridad/pin/eliminar" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <div class="col-12 col-md-7">
                            <label class="form-label small" for="pin-remove-password">
                                <?= e(__('common.fields.current_password')) ?>
                            </label>
                            <input type="password" class="form-control" id="pin-remove-password"
                                   name="current_password" autocomplete="current-password" required>
                        </div>
                        <div class="col-12 col-md-5">
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <?= e(__('auth.security.pin_remove')) ?>
                            </button>
                        </div>
                    </form>

                    <div class="pin-login-box mt-3">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-box-arrow-in-right fs-5 text-secondary" aria-hidden="true"></i>
                            <div class="flex-grow-1">
                                <p class="fw-semibold small mb-1">
                                    <?= e(__('auth.security.pin_login_title')) ?>
                                    <span class="badge <?= $pinLogin ? 'text-bg-success' : 'text-bg-secondary' ?> ms-1">
                                        <?= e(__($pinLogin ? 'auth.security.pin_login_on' : 'auth.security.pin_login_off')) ?>
                                    </span>
                                </p>
                                <p class="small text-secondary mb-2"><?= e(__('auth.security.pin_login_hint')) ?></p>
                            </div>
                        </div>
                        <?php if ($pinLogin): ?>
                            <form method="post" action="/ajustes/seguridad/pin/entrar" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="activar" value="0">
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(__('auth.security.pin_login_disable')) ?></button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="/ajustes/seguridad/pin/entrar" class="row g-2 align-items-end">
                                <?= csrf_field() ?>
                                <input type="hidden" name="activar" value="1">
                                <div class="col-12 col-md-7">
                                    <label class="form-label small" for="pin-login-password"><?= e(__('common.fields.current_password')) ?></label>
                                    <input type="password" class="form-control" id="pin-login-password" name="current_password"
                                           autocomplete="current-password" required>
                                </div>
                                <div class="col-12 col-md-5">
                                    <button type="submit" class="btn btn-outline-primary w-100"><?= e(__('auth.security.pin_login_enable')) ?></button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="small text-secondary mb-3"><?= e(__('auth.security.pin_login_after')) ?></p>
                    <form method="post" action="/ajustes/seguridad/pin" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <div class="col-12 col-md-6">
                            <label class="form-label small" for="pin-password">
                                <?= e(__('common.fields.current_password')) ?>
                            </label>
                            <input type="password" class="form-control" id="pin-password"
                                   name="current_password" autocomplete="current-password" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small" for="pin-value"><?= e(__('auth.security.pin_label')) ?></label>
                            <input type="password" class="form-control" id="pin-value" name="pin"
                                   inputmode="numeric" pattern="[0-9]*" maxlength="<?= $pinLength ?>"
                                   autocomplete="off" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small" for="pin-confirm">
                                <?= e(__('auth.security.pin_confirm')) ?>
                            </label>
                            <input type="password" class="form-control" id="pin-confirm" name="pin_confirmation"
                                   inputmode="numeric" pattern="[0-9]*" maxlength="<?= $pinLength ?>"
                                   autocomplete="off" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-primary">
                                <?= e(__('auth.security.pin_save')) ?>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <hr class="my-4">

                <h2 class="h5 mb-1">
                    <i class="bi bi-fingerprint me-1" aria-hidden="true"></i><?= e(__('auth.passkeys.title')) ?>
                </h2>
                <p class="text-secondary small mb-3"><?= e(__('auth.passkeys.hint')) ?></p>

                <?php if ($passkeys === []): ?>
                    <p class="small text-secondary mb-3"><?= e(__('auth.passkeys.empty')) ?></p>
                <?php else: ?>
                    <ul class="list-unstyled mb-3">
                        <?php foreach ($passkeys as $passkey): ?>
                            <li class="d-flex align-items-center gap-2 py-2 border-bottom">
                                <i class="bi bi-phone fs-5 text-secondary" aria-hidden="true"></i>
                                <div class="flex-grow-1 small">
                                    <span class="fw-semibold d-block"><?= e((string) $passkey['nombre']) ?></span>
                                    <span class="text-secondary">
                                        <?= e(__('auth.passkeys.added_at', [':date' => format_datetime($passkey['created_at'] ?? null)])) ?>
                                        ·
                                        <?= e(!empty($passkey['ultimo_uso_at'])
                                            ? __('auth.passkeys.used_at', [':date' => format_datetime($passkey['ultimo_uso_at'])])
                                            : __('auth.passkeys.never_used')) ?>
                                    </span>
                                </div>
                                <form method="post" action="/ajustes/seguridad/huella/eliminar" class="m-0"
                                      data-confirm="<?= e(__('auth.passkeys.confirm_remove')) ?>" data-confirm-danger>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $passkey['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('auth.passkeys.remove')) ?></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" action="/ajustes/seguridad/huella" class="row g-2 align-items-end"
                      data-passkey="create" data-passkey-options="/ajustes/seguridad/huella/opciones">
                    <?= csrf_field() ?>
                    <?php foreach (['credential_id', 'client_data', 'authenticator_data', 'public_key', 'algorithm'] as $field): ?>
                        <input type="hidden" name="<?= e($field) ?>" value="">
                    <?php endforeach; ?>
                    <div class="col-12 col-md-6">
                        <label class="form-label small" for="passkey-name"><?= e(__('auth.passkeys.name')) ?></label>
                        <input type="text" class="form-control" id="passkey-name" name="nombre" maxlength="80"
                               placeholder="<?= e(__('auth.passkeys.name_placeholder')) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small" for="passkey-password"><?= e(__('common.fields.current_password')) ?></label>
                        <input type="password" class="form-control" id="passkey-password" name="current_password"
                               autocomplete="current-password" required>
                    </div>
                    <div class="col-12">
                        <p class="small text-secondary mb-2"><?= e(__('auth.passkeys.password_hint')) ?></p>
                        <button type="button" class="btn btn-outline-primary" data-passkey-start>
                            <i class="bi bi-fingerprint me-1" aria-hidden="true"></i><?= e(__('auth.passkeys.add')) ?>
                        </button>
                        <p class="small text-danger mt-2 mb-0" data-passkey-error role="alert" hidden></p>
                    </div>
                </form>

                <hr class="my-4">

                <h2 class="h5 mb-1"><?= e(__('auth.security.questions_title')) ?></h2>
                <p class="text-secondary small mb-3"><?= e(__('auth.security.questions_hint')) ?></p>

                <form method="post" action="/ajustes/seguridad/preguntas" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-4">
                        <label for="current_password" class="form-label"><?= e(__('common.fields.current_password')) ?></label>
                        <input type="password" class="form-control" id="current_password" name="current_password"
                               autocomplete="current-password" required>
                        <div class="form-text"><?= e(__('auth.security.current_password_hint')) ?></div>
                    </div>

                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <?php $question = $questions[$i] ?? ['pregunta' => '', 'respuesta' => '']; ?>
                        <div class="row g-2 mb-2 align-items-end">
                            <div class="col-md-7">
                                <label class="form-label small" for="question_<?= $i ?>">
                                    <?= e(__('auth.fields.question', [':number' => (string) ($i + 1)])) ?>
                                </label>
                                <input type="text" class="form-control" id="question_<?= $i ?>"
                                       name="questions[<?= $i ?>][pregunta]"
                                       value="<?= e($question['pregunta'] ?? '') ?>" maxlength="255" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small" for="answer_<?= $i ?>">
                                    <?= e(__('auth.fields.answer', [':number' => (string) ($i + 1)])) ?>
                                </label>
                                <input type="text" class="form-control" id="answer_<?= $i ?>"
                                       name="questions[<?= $i ?>][respuesta]"
                                       value="<?= e($question['respuesta'] ?? '') ?>" maxlength="255" required autocomplete="off">
                            </div>
                        </div>
                    <?php endfor; ?>

                    <button type="submit" class="btn btn-primary mt-3"><?= e(__('auth.security.save_questions')) ?></button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5 mb-1"><?= e(__('auth.security.sessions_title')) ?></h2>
                <p class="text-secondary small mb-3">
                    <?= e(__('auth.security.sessions_hint', [':count' => (string) $sessionCount])) ?>
                </p>
                <p class="small text-secondary"><?= e(__('auth.security.close_all_hint')) ?></p>

                <?php if ($sessions !== []): ?>
                    <ul class="list-unstyled small mb-3">
                        <?php foreach ($sessions as $session): ?>
                            <li class="mb-2 pb-2 border-bottom">
                                <span class="d-block text-break">
                                    <?= e(mb_substr((string) ($session['user_agent'] ?? __('auth.security.unknown_device')), 0, 120)) ?>
                                </span>
                                <span class="text-secondary">
                                    <?= e((string) ($session['ip'] ?? '—')) ?>
                                    · <?= e(format_datetime($session['last_activity'] ?? null)) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" action="/ajustes/seguridad/cerrar-sesiones"
                      data-confirm="<?= e(__('auth.security.close_all_hint')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger w-100"><?= e(__('auth.security.close_all')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
