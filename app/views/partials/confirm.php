<?php
/**
 * Diálogo único de confirmación y avisos (sustituye confirm()/alert()).
 *
 * En móvil es un bottom sheet; desde 768px se ve como modal centrado
 * (estilos .app-sheet en _finance.scss). Lo controla assets/js/05-dialogs.js.
 */
?>
<div class="offcanvas offcanvas-bottom app-sheet app-dialog" tabindex="-1" id="appDialog"
     aria-labelledby="appDialogTitle" aria-describedby="appDialogMessage" data-bs-backdrop="static" data-app-dialog>
    <div class="offcanvas-body text-center pt-4">
        <span class="app-dialog-icon mb-3" aria-hidden="true" data-dialog-icon>
            <i class="bi bi-question-lg"></i>
        </span>
        <h2 class="h5 mb-2" id="appDialogTitle" data-dialog-title><?= e(__('common.dialog.confirm_title')) ?></h2>
        <p class="text-secondary mb-4" id="appDialogMessage" data-dialog-message></p>
        <div class="d-flex flex-column-reverse flex-sm-row justify-content-center gap-2">
            <button type="button" class="btn btn-outline-secondary" data-dialog-cancel>
                <?= e(__('common.actions.cancel')) ?>
            </button>
            <button type="button" class="btn btn-primary" data-dialog-ok
                    data-label-confirm="<?= e(__('common.dialog.confirm')) ?>"
                    data-label-ok="<?= e(__('common.dialog.ok')) ?>"
                    data-label-danger="<?= e(__('common.dialog.danger')) ?>">
                <?= e(__('common.dialog.confirm')) ?>
            </button>
        </div>
    </div>
</div>
<div hidden data-dialog-strings
     data-confirm-title="<?= e(__('common.dialog.confirm_title')) ?>"
     data-danger-title="<?= e(__('common.dialog.danger_title')) ?>"
     data-notice-title="<?= e(__('common.dialog.notice_title')) ?>"></div>
