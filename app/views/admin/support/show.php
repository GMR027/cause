<?php
$conversation = $conversation ?? [];
$messages = $messages ?? [];
$closed = (string) ($conversation['estado'] ?? 'abierta') === 'cerrada';
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('support.admin.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e((string) ($conversation['asunto'] ?? '')) ?></h1>
        <p class="text-secondary mb-0">
            <?= e((string) ($conversation['alias'] ?? '')) ?>
            · <?= e((string) ($conversation['email'] ?? '')) ?>
            · <?= e(format_datetime($conversation['created_at'] ?? null)) ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-start">
        <span class="badge <?= $closed ? 'text-bg-secondary' : 'text-bg-success' ?>">
            <?= e(__('support.status.' . (string) ($conversation['estado'] ?? 'abierta'))) ?>
        </span>
        <a href="/admin/soporte" class="btn btn-outline-secondary"><?= e(__('support.admin.back')) ?></a>
    </div>
</div>

<div class="surface-card mb-3">
    <div class="support-thread">
        <?php foreach ($messages as $message): ?>
            <?php $isAdmin = (string) $message['autor'] === 'admin'; ?>
            <div class="support-message <?= $isAdmin ? 'is-admin' : 'is-user' ?>">
                <div class="support-message-meta">
                    <strong>
                        <?= e($isAdmin ? __('support.admin_name') : (string) ($conversation['alias'] ?? __('support.admin.user'))) ?>
                    </strong>
                    <span><?= e(format_datetime($message['created_at'] ?? null)) ?></span>
                </div>
                <div class="support-message-body">
                    <?= nl2br(e((string) $message['mensaje'])) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="surface-card h-100">
            <h2 class="h5 mb-1"><?= e(__('support.admin.reply_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('support.admin.reply_hint')) ?></p>

            <form method="post" action="/admin/soporte/responder" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) ($conversation['id'] ?? 0) ?>">
                <div class="col-12">
                    <label class="form-label" for="admin-support-reply"><?= e(__('support.fields.message')) ?></label>
                    <textarea class="form-control" id="admin-support-reply" name="mensaje" rows="5"
                              maxlength="4000" required placeholder="<?= e(__('support.admin.reply_placeholder')) ?>"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><?= e(__('support.admin.reply_submit')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="surface-card h-100">
            <h2 class="h6 mb-2"><?= e(__('support.admin.actions')) ?></h2>
            <form method="post" action="/admin/soporte/estado" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) ($conversation['id'] ?? 0) ?>">
                <input type="hidden" name="estado" value="<?= $closed ? 'abierta' : 'cerrada' ?>">
                <button type="submit" class="btn <?= $closed ? 'btn-outline-primary' : 'btn-outline-secondary' ?> w-100">
                    <?= e($closed ? __('support.admin.reopen') : __('support.admin.close')) ?>
                </button>
            </form>
            <p class="small text-secondary mt-3 mb-0"><?= e(__('support.admin.status_hint')) ?></p>
        </div>
    </div>
</div>
