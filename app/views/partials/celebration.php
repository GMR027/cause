<?php

$pendingCelebration = $pendingCelebration ?? null;
$gamificationEnabled = $gamificationEnabled ?? true;
$currentUser = $currentUser ?? [];

if ($pendingCelebration === null || !$gamificationEnabled) {
    return;
}

$type = (string) ($pendingCelebration['tipo'] ?? '');
$badgeTitle = __('gamification.badges.' . $type . '.title');
$body = __('gamification.celebration.body', [':badge' => $badgeTitle]);
$tone = (string) ($currentUser['tono_notificaciones'] ?? 'neutral');

if ($tone !== 'neutral') {
    $prefixKey = 'notifications.tones.' . $tone;
    $prefix = __($prefixKey);

    if ($prefix !== $prefixKey) {
        $body = $prefix . ' ' . $body;
    }
}
?>
<div class="modal fade celebration-modal" id="celebrationModal" tabindex="-1"
     aria-labelledby="celebrationTitle" aria-hidden="true"
     data-celebration-id="<?= (int) ($pendingCelebration['id'] ?? 0) ?>"
     data-celebration-url="/logros/celebracion">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center">
            <div class="modal-body py-4">
                <div class="celebration-burst" aria-hidden="true">
                    <i class="bi bi-award-fill"></i>
                </div>
                <h2 class="h5 mt-3 mb-1" id="celebrationTitle"><?= e(__('gamification.celebration.title')) ?></h2>
                <p class="small text-secondary mb-3"><?= e($body) ?></p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        <?= e(__('gamification.celebration.skip')) ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal">
                        <?= e(__('gamification.celebration.continue')) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
