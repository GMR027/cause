<?php

$enabled = (bool) ($enabled ?? false);
$streak = $streak ?? [];
$level = $level ?? [];
$badges = $badges ?? [];
$challenge = $challenge ?? null;
$social = $social ?? [];
$past = $past ?? [];
$currency = (string) ($currentUser['moneda_activa'] ?? 'MXN');
$pct = static fn (float $value): string => rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
$levelNumber = (int) ($level['nivel'] ?? 0);
$hasLevel = !empty($level['has_data']);
$levelPercent = (float) ($level['porcentaje'] ?? 0);
$levelName = $levelNumber >= 1
    ? __('gamification.levels.names.' . $levelNumber)
    : __('gamification.levels.no_data');
$detail = (array) ($level['detalle'] ?? []);
$next = $detail['next'] ?? null;
$periods = (array) ($detail['periods'] ?? []);
$streakState = (string) ($streak['state'] ?? 'activa');
$streakCount = (int) ($streak['count'] ?? 0);
$streakBest = (int) ($streak['best'] ?? 0);
$challengeState = (string) ($challenge['estado'] ?? '');
$challengeVisible = $challenge !== null && !in_array($challengeState, ['expirado', 'descartado'], true);
$challengePercent = $challenge !== null ? min(100, max(0, (float) ($challenge['progreso'] ?? 0))) : 0;
$socialDirection = (string) ($social['direction'] ?? 'off');
$dateOnly = static fn (?string $date): string => $date !== null && trim($date) !== ''
    ? format_date($date . ' 12:00:00')
    : '—';
$badgeIcons = [
    'primera_meta' => 'bi-bullseye',
    'primer_mes_completo' => 'bi-calendar2-check',
    'emergencia_50' => 'bi-shield-half',
    'emergencia_100' => 'bi-shield-check',
    'racha_30' => 'bi-fire',
];
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('gamification.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('gamification.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('gamification.subtitle')) ?></p>
    </div>
    <a href="/ajustes/perfil" class="btn btn-outline-secondary">
        <i class="bi bi-sliders me-1" aria-hidden="true"></i>
        <?= e(__('settings.perfil.title')) ?>
    </a>
</div>

<?php if (!$enabled): ?>
    <div class="alert alert-secondary py-2 small" role="status" data-dismissible data-dismiss-key="progress-info">
        <i class="bi bi-eye-slash me-1" aria-hidden="true"></i>
        <?= e(__('gamification.enabled_hint')) ?>
        <a href="/ajustes/perfil" class="ms-1"><?= e(__('gamification.enabled')) ?></a>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12 <?= $enabled ? 'col-xl-7' : '' ?>">
        <div class="surface-card level-card h-100">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                <div>
                    <span class="small text-secondary d-block"><?= e(__('gamification.levels.title')) ?></span>
                    <h2 class="h4 mb-0"><?= e($levelName) ?></h2>
                </div>
                <?php if ($hasLevel): ?>
                    <span class="badge text-bg-light border">
                        <?= e(__('gamification.levels.current', [':level' => (string) $levelNumber])) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($hasLevel): ?>
                <p class="small text-secondary mb-2">
                    <?= e(__('gamification.levels.percent', [':percent' => $pct($levelPercent)])) ?>
                </p>
                <div class="progress level-progress mb-2" role="progressbar"
                     aria-valuenow="<?= (int) round($levelPercent) ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" style="width: <?= e((string) min(100, max(0, $levelPercent))) ?>%"></div>
                </div>

                <?php if ($next !== null): ?>
                    <p class="small mb-2">
                        <?= e(__('gamification.levels.next', [
                            ':missing' => $pct((float) ($next['missing'] ?? 0)),
                            ':level' => __((string) ($next['key'] ?? '')),
                        ])) ?>
                    </p>
                <?php else: ?>
                    <p class="small text-success mb-2"><?= e(__('gamification.levels.max')) ?></p>
                <?php endif; ?>

                <p class="small text-secondary mb-2">
                    <i class="bi bi-lock me-1" aria-hidden="true"></i>
                    <?= e(__('gamification.levels.private')) ?>
                </p>

                <?php if ($periods !== []): ?>
                    <button class="btn btn-sm btn-outline-secondary" type="button"
                            data-bs-toggle="collapse" data-bs-target="#levelBreakdown"
                            aria-expanded="false" aria-controls="levelBreakdown">
                        <?= e(__('gamification.levels.breakdown')) ?>
                    </button>

                    <div class="collapse mt-3" id="levelBreakdown">
                        <p class="small text-secondary"><?= e(__('gamification.levels.breakdown_hint')) ?></p>

                        <?php foreach ($periods as $period): ?>
                            <div class="level-period">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <span class="fw-semibold small">
                                        <?= e(__('gamification.levels.period', [
                                            ':range' => format_date($period['inicio'] ?? null)
                                                . ' – ' . format_date($period['fin'] ?? null),
                                        ])) ?>
                                    </span>
                                    <span class="badge text-bg-light border">
                                        <?= e($pct((float) ($period['percent'] ?? 0))) ?>%
                                    </span>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col"><?= e(__('gamification.levels.category')) ?></th>
                                                <th scope="col" class="text-end"><?= e(__('gamification.levels.assigned')) ?></th>
                                                <th scope="col" class="text-end"><?= e(__('gamification.levels.spent')) ?></th>
                                                <th scope="col" class="text-end"><?= e(__('gamification.levels.compliance')) ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ((array) ($period['categories'] ?? []) as $category): ?>
                                                <?php $isControlled = (string) ($category['tipo'] ?? '') === 'controlada'; ?>
                                                <tr>
                                                    <td><?= e((string) ($category['nombre'] ?? '')) ?></td>
                                                    <td class="text-end"><?= money_html((int) ($category['asignado_centavos'] ?? 0), $currency) ?></td>
                                                    <td class="text-end"><?= money_html((int) ($category['actual_centavos'] ?? 0), $currency) ?></td>
                                                    <td class="text-end">
                                                        <span class="badge <?= (float) ($category['cumplimiento'] ?? 0) >= 100 ? 'text-bg-success' : 'text-bg-warning' ?>">
                                                            <?= e($pct((float) ($category['cumplimiento'] ?? 0))) ?>%
                                                        </span>
                                                        <?php if (!$isControlled): ?>
                                                            <span class="small text-secondary d-block">
                                                                <?= e(__('gamification.levels.contributed')) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="mb-1 fw-semibold"><?= e(__('gamification.levels.no_data')) ?></p>
                <p class="small text-secondary mb-0"><?= e(__('gamification.levels.no_data_hint')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($enabled): ?>
    <div class="col-12 col-xl-5">
        <div class="surface-card h-100">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <div>
                    <span class="small text-secondary d-block"><?= e(__('gamification.streak.title')) ?></span>
                    <h2 class="h4 mb-0">
                        <?php if (!$enabled): ?>
                            <i class="bi bi-fire text-secondary" aria-hidden="true"></i>
                        <?php else: ?>
                            <i class="bi bi-fire text-warning" aria-hidden="true"></i>
                        <?php endif; ?>
                        <?= $streakCount > 0 ? (int) $streakCount : 0 ?>
                    </h2>
                </div>
                <?php if ($enabled && $streakCount > 0 && $streakState === 'activa'): ?>
                    <span class="badge text-bg-success"><?= e(__('gamification.streak.best', [':count' => (string) $streakBest])) ?></span>
                <?php endif; ?>
            </div>

            <?php if (!$enabled): ?>
                <p class="small text-secondary mb-0"><?= e(__('gamification.enabled_hint')) ?></p>
            <?php elseif ($streakState === 'pausa'): ?>
                <p class="fw-semibold mb-1"><?= e(__('gamification.streak.paused')) ?></p>
                <p class="small text-secondary mb-2"><?= e(__('gamification.streak.paused_hint')) ?></p>
                <span class="badge text-bg-warning">
                    <?= e($streakCount === 1
                        ? __('gamification.streak.active_one')
                        : __('gamification.streak.active', [':count' => (string) $streakCount])) ?>
                </span>
            <?php elseif ($streakState === 'rota'): ?>
                <p class="fw-semibold mb-1"><?= e(__('gamification.streak.broken')) ?></p>
                <p class="small text-secondary mb-0">
                    <?= e(__('gamification.streak.broken_hint', [':count' => (string) $streakBest])) ?>
                </p>
            <?php elseif ($streakCount > 0): ?>
                <p class="fw-semibold mb-1">
                    <?= e($streakCount === 1
                        ? __('gamification.streak.active_one')
                        : __('gamification.streak.active', [':count' => (string) $streakCount])) ?>
                </p>
                <p class="small text-secondary mb-0"><?= e(__('gamification.streak.best', [':count' => (string) $streakBest])) ?></p>
            <?php else: ?>
                <p class="fw-semibold mb-1"><?= e(__('gamification.streak.none')) ?></p>
                <p class="small text-secondary mb-0"><?= e(__('gamification.streak.none_hint')) ?></p>
            <?php endif; ?>

            <?php if ($streakCount < 30 && in_array($streakState, ['activa', 'pausa'], true)): ?>
                <p class="small text-secondary mb-0 mt-2">
                    <?= e(__('gamification.streak.next_badge', [':count' => (string) max(1, 30 - $streakCount)])) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($enabled): ?>
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-7">
            <div class="surface-card h-100">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <div>
                        <span class="small text-secondary d-block"><?= e(__('gamification.challenge.title')) ?></span>
                        <h2 class="h5 mb-0">
                            <?php if ($challenge !== null): ?>
                                <?= e(__('gamification.challenge.types.' . (string) $challenge['tipo'], [
                                    ':percent' => $pct((float) ($challenge['objetivo'] ?? 0)),
                                ])) ?>
                            <?php else: ?>
                                <?= e(__('gamification.challenge.empty')) ?>
                            <?php endif; ?>
                        </h2>
                    </div>
                    <?php if ($challengeState === 'completado'): ?>
                        <span class="badge text-bg-success"><?= e(__('gamification.challenge.completed')) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($challengeVisible): ?>
                    <?php if (str_starts_with((string) $challenge['tipo'], 'cumple_')): ?>
                        <p class="small text-secondary mb-2">
                            <?= e(__('gamification.challenge.target', [
                                ':percent' => $pct((float) ($challenge['objetivo'] ?? 0)),
                            ])) ?>
                        </p>
                    <?php elseif ((string) $challenge['tipo'] === 'registra_todo'): ?>
                        <p class="small text-secondary mb-2"><?= e(__('gamification.challenge.registra_todo_hint')) ?></p>
                    <?php endif; ?>
                    <div class="progress challenge-progress mb-2" role="progressbar"
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
                            <input type="hidden" name="origen" value="logros">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <?= e(__('gamification.challenge.dismiss')) ?>
                            </button>
                        </form>
                    </div>
                <?php elseif ($challengeState === 'descartado'): ?>
                    <p class="small text-secondary mb-0"><?= e(__('gamification.challenge.dismissed')) ?></p>
                <?php else: ?>
                    <p class="small text-secondary mb-0"><?= e(__('gamification.challenge.empty')) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="surface-card h-100">
                <span class="small text-secondary d-block"><?= e(__('gamification.social.title')) ?></span>
                <h2 class="h5 mb-2"><?= e(__('gamification.social.title')) ?></h2>

                <?php if ($socialDirection === 'off'): ?>
                    <p class="small text-secondary mb-2"><?= e(__('gamification.social.off')) ?></p>
                    <a href="/ajustes/perfil" class="btn btn-sm btn-outline-secondary">
                        <?= e(__('gamification.social.title')) ?>
                    </a>
                <?php elseif ($socialDirection === 'insufficient'): ?>
                    <p class="fw-semibold mb-1"><?= e(__('gamification.social.insufficient')) ?></p>
                    <p class="small text-secondary mb-0"><?= e(__('gamification.social.insufficient_hint')) ?></p>
                <?php elseif ($socialDirection === 'no_data'): ?>
                    <p class="small text-secondary mb-0"><?= e(__('gamification.social.no_data')) ?></p>
                <?php else: ?>
                    <p class="mb-2">
                        <?= e(__('gamification.social.' . $socialDirection)) ?>
                    </p>
                <?php endif; ?>

                <p class="small text-secondary mb-0 mt-2">
                    <i class="bi bi-incognito me-1" aria-hidden="true"></i>
                    <?= e(__('gamification.social.privacy')) ?>
                </p>
            </div>
        </div>
    </div>

    <div class="surface-card mb-4">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <div>
                <span class="small text-secondary d-block"><?= e(__('gamification.badges.subtitle')) ?></span>
                <h2 class="h5 mb-0"><?= e(__('gamification.badges.title')) ?></h2>
            </div>
            <span class="badge text-bg-light border">
                <?= count(array_filter($badges, static fn (array $badge): bool => !empty($badge['unlocked']))) ?> / <?= count($badges) ?>
            </span>
        </div>

        <div class="badge-grid">
            <?php foreach ($badges as $badge): ?>
                <?php $unlocked = !empty($badge['unlocked']); ?>
                <div class="badge-tile <?= $unlocked ? 'is-unlocked' : 'is-locked' ?>">
                    <span class="badge-tile-icon" aria-hidden="true">
                        <i class="bi <?= e($badgeIcons[(string) $badge['tipo']] ?? 'bi-award') ?>"></i>
                    </span>
                    <span class="badge-tile-body">
                        <span class="fw-semibold d-block">
                            <?= e(__('gamification.badges.' . (string) $badge['tipo'] . '.title')) ?>
                        </span>
                        <span class="small text-secondary d-block">
                            <?= e(__('gamification.badges.' . (string) $badge['tipo'] . '.body')) ?>
                        </span>
                        <span class="small <?= $unlocked ? 'text-success' : 'text-secondary' ?> d-block mt-1">
                            <?= $unlocked
                                ? e(__('gamification.badges.unlocked', [':date' => $dateOnly($badge['fecha_logro'] ?? null)]))
                                : e(__('gamification.badges.locked')) ?>
                        </span>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="surface-card">
    <?= partial('partials/past-comparison', ['past' => $past, 'currency' => $currency]) ?>
</div>
