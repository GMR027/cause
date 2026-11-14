<?php
$score = $score ?? [];
$hasData = !empty($score['has_data']);
$trend = $score['trend'] ?? null;
?>
<div class="surface-card score-widget mb-4" id="score">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <span class="small text-secondary d-block"><?= e(__('score.home_label')) ?></span>
            <h2 class="h3 mb-1">
                <?php if ($hasData): ?>
                    <?= e(__('score.summary', [':score' => (string) (int) $score['score']])) ?>
                <?php else: ?>
                    <?= e(__('score.title')) ?>
                <?php endif; ?>
            </h2>
            <?php if ($hasData && $trend !== null): ?>
                <span class="badge text-bg-light border"><?= e(__('score.trend.' . $trend)) ?></span>
            <?php endif; ?>
        </div>
        <a href="/reportes#score" class="btn btn-sm btn-outline-secondary">
            <?= e(__('score.view_detail')) ?>
        </a>
    </div>

    <?php if ($hasData): ?>
        <p class="small text-secondary mb-0 mt-2"><?= e(__('score.disclaimer')) ?> · <?= e(__('score.private')) ?></p>
    <?php else: ?>
        <p class="mb-1 mt-2"><?= e(__('score.no_data')) ?></p>
        <p class="small text-secondary mb-0"><?= e(__('score.no_data_hint')) ?></p>
    <?php endif; ?>
</div>
