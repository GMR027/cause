<?php
$conversations = $conversations ?? [];
$unread = (int) ($unread ?? 0);
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('support.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('support.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('support.subtitle')) ?></p>
    </div>
    <?php if ($unread > 0): ?>
        <span class="badge text-bg-primary align-self-start"><?= e(__('support.unread', [':count' => (string) $unread])) ?></span>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-5">
        <div class="surface-card">
            <h2 class="h5 mb-1"><?= e(__('support.new_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('support.new_hint')) ?></p>

            <form method="post" action="/soporte" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-12">
                    <label class="form-label" for="support-subject"><?= e(__('support.fields.subject')) ?></label>
                    <input type="text" class="form-control" id="support-subject" name="asunto"
                           maxlength="160" required placeholder="<?= e(__('support.fields.subject_placeholder')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="support-message"><?= e(__('support.fields.message')) ?></label>
                    <textarea class="form-control" id="support-message" name="mensaje" rows="5"
                              maxlength="4000" required placeholder="<?= e(__('support.fields.message_placeholder')) ?>"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary w-100"><?= e(__('support.new_submit')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="surface-card h-100">
            <h2 class="h5 mb-1"><?= e(__('support.inbox_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('support.inbox_hint')) ?></p>

            <?php if ($conversations === []): ?>
                <div class="text-center py-4">
                    <i class="bi bi-chat-square-text fs-2 text-secondary" aria-hidden="true"></i>
                    <p class="mt-2 mb-1 fw-semibold"><?= e(__('support.empty')) ?></p>
                    <p class="small text-secondary mb-0"><?= e(__('support.empty_hint')) ?></p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($conversations as $conversation): ?>
                        <a href="/soporte/ver?id=<?= (int) $conversation['id'] ?>"
                           class="border rounded-3 p-3 text-decoration-none text-reset d-flex flex-wrap justify-content-between gap-2">
                            <span class="flex-grow-1">
                                <span class="fw-semibold d-block">
                                    <?= e((string) $conversation['asunto']) ?>
                                    <?php if ((int) ($conversation['no_leidos'] ?? 0) > 0): ?>
                                        <span class="badge text-bg-primary ms-1">
                                            <?= e(__('support.unread_short', [':count' => (string) $conversation['no_leidos']])) ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <span class="small text-secondary d-block text-truncate">
                                    <?= e((string) ($conversation['ultimo_mensaje'] ?? '')) ?>
                                </span>
                            </span>
                            <span class="text-end small text-secondary">
                                <span class="badge <?= (string) $conversation['estado'] === 'cerrada' ? 'text-bg-secondary' : 'text-bg-success' ?> d-block mb-1">
                                    <?= e(__('support.status.' . (string) $conversation['estado'])) ?>
                                </span>
                                <?= e(format_datetime($conversation['ultimo_mensaje_at'] ?? $conversation['created_at'])) ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
