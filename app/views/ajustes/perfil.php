<?php

$user = $user ?? [];
$lang = $lang ?? 'es';
$currentUri = $_SERVER['REQUEST_URI'] ?? '/ajustes/perfil';
$initial = mb_strtoupper(mb_substr((string) ($user['alias'] ?? '?'), 0, 1));
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('settings.perfil.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('settings.perfil.subtitle')) ?></p>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="surface-card">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="app-avatar app-avatar-lg" aria-hidden="true"><?= e($initial) ?></span>
                <div>
                    <h2 class="h5 mb-0"><?= e((string) ($user['alias'] ?? '')) ?></h2>
                    <p class="small text-secondary mb-0"><?= e((string) ($user['email'] ?? '')) ?></p>
                </div>
            </div>

            <dl class="row small mb-0">
                <dt class="col-5 text-secondary fw-normal"><?= e(__('common.fields.rol')) ?></dt>
                <dd class="col-7 mb-2"><?= e(__('common.roles.' . (string) ($user['rol'] ?? 'user'))) ?></dd>

                <dt class="col-5 text-secondary fw-normal"><?= e(__('settings.perfil.member_since')) ?></dt>
                <dd class="col-7 mb-0"><?= e(format_date($user['created_at'] ?? null)) ?></dd>
            </dl>

            <hr class="my-4">

            <h2 class="h6 mb-1"><?= e(__('reports.cash.title')) ?></h2>
            <p class="small text-secondary mb-2"><?= e(__('reports.cash.hint')) ?></p>
            <form method="post" action="/ajustes/perfil/efectivo" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-12 col-md-8">
                    <label class="form-label small" for="profile-cash"><?= e(__('reports.cash.label')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" inputmode="decimal" class="form-control" id="profile-cash" name="efectivo"
                               value="<?= (int) ($user['efectivo_manual_centavos'] ?? 0) > 0
                                   ? e(number_format((int) $user['efectivo_manual_centavos'] / 100, 2, '.', ''))
                                   : '' ?>"
                               placeholder="0.00">
                        <span class="input-group-text"><?= e((string) ($user['moneda_activa'] ?? 'MXN')) ?></span>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                        <?= e(__('reports.cash.save')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="surface-card">
            <h2 class="h5 mb-1"><?= e(__('settings.perfil.language')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('settings.perfil.language_hint')) ?></p>

            <div class="option-grid option-grid-2">
                <a class="option-card <?= $lang === 'es' ? 'is-selected' : '' ?>"
                   href="/idioma?lang=es&next=<?= e(rawurlencode($currentUri)) ?>">
                    <span class="option-body">
                        <span class="option-title"><?= e(__('common.spanish')) ?></span>
                        <span class="option-hint">ES</span>
                    </span>
                </a>
                <a class="option-card <?= $lang === 'en' ? 'is-selected' : '' ?>"
                   href="/idioma?lang=en&next=<?= e(rawurlencode($currentUri)) ?>">
                    <span class="option-body">
                        <span class="option-title"><?= e(__('common.english')) ?></span>
                        <span class="option-hint">EN</span>
                    </span>
                </a>
            </div>

            <a href="/ajustes/monedas" class="btn btn-outline-secondary w-100 mt-3">
                <i class="bi bi-currency-exchange me-1" aria-hidden="true"></i><?= e(__('currency.title')) ?>
            </a>

            <a href="/ajustes/temas" class="btn btn-outline-secondary w-100 mt-3">
                <i class="bi bi-palette me-1" aria-hidden="true"></i><?= e(__('settings.temas.title')) ?>
            </a>
        </div>
    </div>
</div>

<?php
$gamification = $gamification ?? [];
$gamificationEnabled = !empty($gamification['enabled']);
$level = $gamification['level'] ?? [];
$streak = $gamification['streak'] ?? [];
$badges = $gamification['badges'] ?? [];
$social = $gamification['social'] ?? [];
$socialEnabled = !empty($social['enabled']);
$hasLevel = !empty($level['has_data']);
$levelNumber = (int) ($level['nivel'] ?? 0);
$levelPercent = (float) ($level['porcentaje'] ?? 0);
$levelName = $levelNumber >= 1
    ? __('gamification.levels.names.' . $levelNumber)
    : __('gamification.levels.no_data');
$unlockedBadges = count(array_filter($badges, static fn (array $badge): bool => !empty($badge['unlocked'])));
$pct = static fn (float $value): string => rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
?>
<div class="row g-3 mt-3">
    <div class="col-12">
        <div class="surface-card">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <span class="badge phase-badge mb-1"><?= e(__('gamification.badge')) ?></span>
                    <h2 class="h5 mb-0"><?= e(__('gamification.title')) ?></h2>
                </div>
                <a href="/logros" class="btn btn-sm btn-outline-secondary">
                    <?= e(__('gamification.badges.title')) ?>
                    <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                </a>
            </div>

            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <form method="post" action="/ajustes/perfil/gamificacion" class="progress-setting">
                        <?= csrf_field() ?>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="gamification-enabled" name="activo" value="1"
                                   <?= $gamificationEnabled ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="gamification-enabled">
                                <?= e(__('gamification.enabled')) ?>
                            </label>
                        </div>
                        <p class="small text-secondary mb-2"><?= e(__('gamification.enabled_hint')) ?></p>
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <?= e(__('wishlist.actions.save')) ?>
                        </button>
                    </form>

                    <hr>

                    <form method="post" action="/ajustes/perfil/comparacion" class="progress-setting">
                        <?= csrf_field() ?>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="social-comparison" name="activo" value="1"
                                   <?= $socialEnabled ? 'checked' : '' ?>
                                   <?= $gamificationEnabled ? '' : 'disabled' ?>>
                            <label class="form-check-label fw-semibold" for="social-comparison">
                                <?= e(__('gamification.social_label')) ?>
                            </label>
                        </div>
                        <p class="small text-secondary mb-2"><?= e(__('gamification.social_hint')) ?></p>
                        <button type="submit" class="btn btn-sm btn-outline-primary"
                                <?= $gamificationEnabled ? '' : 'disabled' ?>>
                            <?= e(__('wishlist.actions.save')) ?>
                        </button>
                    </form>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="progress-summary">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <span class="small text-secondary"><?= e(__('gamification.levels.title')) ?></span>
                            <span class="badge text-bg-light border">
                                <?= e(__('gamification.streak.best', [':count' => (string) ($streak['best'] ?? 0)])) ?>
                            </span>
                        </div>

                        <?php if ($hasLevel): ?>
                            <p class="h5 mb-1"><?= e($levelName) ?></p>
                            <p class="small text-secondary mb-2">
                                <?= e(__('gamification.levels.percent', [':percent' => $pct($levelPercent)])) ?>
                            </p>
                            <div class="progress level-progress mb-3" role="progressbar"
                                 aria-valuenow="<?= (int) round($levelPercent) ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width: <?= e((string) min(100, max(0, $levelPercent))) ?>%"></div>
                            </div>
                        <?php else: ?>
                            <p class="fw-semibold mb-1"><?= e(__('gamification.levels.no_data')) ?></p>
                            <p class="small text-secondary mb-3"><?= e(__('gamification.levels.no_data_hint')) ?></p>
                        <?php endif; ?>

                        <p class="small text-secondary mb-3">
                            <i class="bi bi-award me-1" aria-hidden="true"></i>
                            <?= e((string) $unlockedBadges) ?> / 5 <?= e(__('gamification.badges.title')) ?>
                        </p>

                        <p class="small text-secondary mb-0">
                            <i class="bi bi-lock me-1" aria-hidden="true"></i>
                            <?= e(__('gamification.levels.private')) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
