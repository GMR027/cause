<?php

$progress = $progress ?? [];
$currency = (string) ($currency ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$enabled = !empty($progress['enabled']);
$streak = (array) ($progress['streak'] ?? []);
$challenge = $progress['challenge'] ?? null;
$past = (array) ($progress['past'] ?? []);
$count = (int) ($streak['count'] ?? 0);
$state = (string) ($streak['state'] ?? 'activa');
$challengeState = (string) ($challenge['estado'] ?? '');
$challengeVisible = $challenge !== null && !in_array($challengeState, ['expirado', 'descartado'], true);
$challengePercent = $challenge !== null ? min(100, max(0, (float) ($challenge['progreso'] ?? 0))) : 0;
$pct = static fn (float $value): string => rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
?>
<div class="surface-card progress-card mb-4">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <span class="badge phase-badge mb-1"><?= e(__('gamification.badge')) ?></span>
            <h2 class="h5 mb-0"><?= e(__('gamification.title')) ?></h2>
        </div>
        <a href="/logros" class="btn btn-sm btn-outline-secondary">
            <?= e(__('gamification.title')) ?>
            <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
        </a>
    </div>

    <div class="row g-3">
        <?php if ($enabled): ?>
            <div class="col-12 col-lg-3">
                <div class="streak-chip <?= $state === 'pausa' ? 'is-paused' : ($state === 'rota' ? 'is-broken' : '') ?>">
                    <span class="streak-chip-icon" aria-hidden="true">
                        <i class="bi bi-fire"></i>
                    </span>
                    <span>
                        <span class="fw-semibold d-block">
                            <?php if ($state === 'pausa'): ?>
                                <?= e(__('gamification.streak.paused')) ?>
                            <?php elseif ($state === 'rota'): ?>
                                <?= e(__('gamification.streak.broken')) ?>
                            <?php elseif ($count === 0): ?>
                                <?= e(__('gamification.streak.none')) ?>
                            <?php else: ?>
                                <?= e($count === 1
                                    ? __('gamification.streak.active_one')
                                    : __('gamification.streak.active', [':count' => (string) $count])) ?>
                            <?php endif; ?>
                        </span>
                        <span class="small text-secondary">
                            <?php if ($state === 'pausa'): ?>
                                <?= e(__('gamification.streak.paused_hint')) ?>
                            <?php elseif ($state === 'rota'): ?>
                                <?= e(__('gamification.streak.broken_hint', [':count' => (string) ($streak['best'] ?? 0)])) ?>
                            <?php else: ?>
                                <?= e(__('gamification.streak.best', [':count' => (string) ($streak['best'] ?? 0)])) ?>
                            <?php endif; ?>
                        </span>
                    </span>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <?php if ($challengeVisible): ?>
                    <div class="challenge-mini">
                        <span class="small text-secondary d-block"><?= e(__('gamification.challenge.title')) ?></span>
                        <span class="fw-semibold d-block mb-1">
                            <?= e(__('gamification.challenge.types.' . (string) $challenge['tipo'], [
                                ':percent' => $pct((float) ($challenge['objetivo'] ?? 0)),
                            ])) ?>
                        </span>
                        <div class="progress challenge-progress mb-1" role="progressbar"
                             aria-valuenow="<?= (int) round($challengePercent) ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar <?= $challengeState === 'completado' ? 'bg-success' : '' ?>"
                                 style="width: <?= e((string) $challengePercent) ?>%"></div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <span class="small text-secondary">
                                <?= e(__('gamification.challenge.progress', [':percent' => $pct($challengePercent)])) ?>
                            </span>
                            <form method="post" action="/logros/reto/descartar" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) ($challenge['id'] ?? 0) ?>">
                                <input type="hidden" name="origen" value="inicio">
                                <button type="submit" class="btn btn-link btn-sm p-0 text-secondary">
                                    <?= e(__('gamification.challenge.dismiss')) ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="challenge-mini">
                        <span class="small text-secondary d-block"><?= e(__('gamification.challenge.title')) ?></span>
                        <span class="small text-secondary d-block mt-1"><?= e(__('gamification.challenge.empty')) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="<?= $enabled ? 'col-12 col-lg-5' : 'col-12' ?>">
            <?= partial('partials/past-comparison', ['past' => $past, 'currency' => $currency]) ?>
        </div>
    </div>
</div>
