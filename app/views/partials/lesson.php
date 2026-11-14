<?php
$lesson = $lesson ?? null;

if (!is_array($lesson)) {
    return;
}

$lessonId = (int) ($lesson['id'] ?? 0);
$code = (string) ($lesson['codigo'] ?? '');
$panelId = 'lesson-' . preg_replace('/[^a-z0-9_]/', '-', $code);
?>
<button type="button" class="btn btn-sm btn-outline-secondary lesson-trigger"
        data-bs-toggle="offcanvas" data-bs-target="#<?= e($panelId) ?>"
        data-lesson-trigger="<?= $lessonId ?>">
    <i class="bi bi-question-circle me-1" aria-hidden="true"></i>
    <?= e(__('lessons.trigger')) ?>
    <?php if (empty($lesson['vista'])): ?>
        <span class="badge text-bg-primary ms-1" data-lesson-new="<?= $lessonId ?>">
            <?= e(__('lessons.new')) ?>
        </span>
    <?php endif; ?>
</button>

<div class="offcanvas offcanvas-end lesson-panel" tabindex="-1" id="<?= e($panelId) ?>"
     aria-labelledby="<?= e($panelId) ?>-title"
     data-lesson-panel="<?= $lessonId ?>" data-lesson-id="<?= $lessonId ?>"
     data-lesson-code="<?= e($code) ?>">
    <div class="offcanvas-header">
        <div>
            <span class="small text-secondary d-block"><?= e(__('lessons.label')) ?></span>
            <h2 class="offcanvas-title h5 mb-0" id="<?= e($panelId) ?>-title">
                <?= e((string) ($lesson['titulo'] ?? '')) ?>
            </h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <p class="lesson-content mb-4"><?= nl2br(e((string) ($lesson['contenido'] ?? ''))) ?></p>
        <button type="button" class="btn btn-primary" data-bs-dismiss="offcanvas">
            <?= e(__('lessons.understood')) ?>
        </button>
    </div>
</div>
