<?php
/**
 * Botón «Entrar/Desbloquear con huella o rostro» (assets/js/08-passkeys.js).
 *
 * Oculto hasta que el JS confirma que el navegador soporta llaves de acceso.
 *
 * @var string $action  ruta que verifica la firma (/login/huella, /desbloquear/huella)
 * @var string $options ruta que entrega el reto
 * @var string $label
 * @var string $next
 */
$next = (string) ($next ?? '');
?>
<form method="post" action="<?= e($action) ?>" class="passkey-block" data-passkey="get"
      data-passkey-options="<?= e($options) ?>" hidden>
    <?= csrf_field() ?>
    <?php if ($next !== ''): ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
    <?php endif; ?>
    <?php foreach (['credential_id', 'client_data', 'authenticator_data', 'signature', 'user_handle'] as $field): ?>
        <input type="hidden" name="<?= e($field) ?>" value="">
    <?php endforeach; ?>
    <div class="passkey-divider"><span><?= e(__('auth.passkeys.or')) ?></span></div>
    <button type="button" class="btn btn-outline-primary w-100 passkey-button" data-passkey-start>
        <i class="bi bi-fingerprint me-1" aria-hidden="true"></i><?= e($label) ?>
    </button>
    <p class="small text-danger mt-2 mb-0" data-passkey-error role="alert" hidden></p>
</form>
