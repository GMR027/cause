<?php

$appName = $config['name'] ?? 'Cauce';
$pageTitle = $title ?? $appName;
$lang = $lang ?? 'es';
$currentUser = $currentUser ?? null;
$currentPath = current_path();
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$theme = $theme ?? (new \Cauce\Services\ThemeService())->resolve($currentUser);
$flashes = \Cauce\Helpers\Flash::pull();
$stepIndex = (int) ($stepIndex ?? 1);
$stepTotal = max(1, (int) ($stepTotal ?? 1));
$progress = (int) round(($stepIndex / $stepTotal) * 100);
?>
<!doctype html>
<html lang="<?= e($lang) ?>" <?= theme_attributes($theme) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · <?= e($appName) ?></title>
    <?= partial('partials/theme-boot', ['theme' => $theme]) ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset_versioned('img/favicon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= asset_versioned('img/favicon-64.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset_versioned('img/apple-touch-icon.png') ?>">
    <link rel="stylesheet" href="<?= asset_versioned('icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_versioned('css/app.min.css') ?>">
</head>
<body class="onboarding-shell">
    <header class="onboarding-header">
        <a href="/" class="app-brand">
            <img class="brand-icon" src="<?= asset_versioned('img/logo-animated.svg') ?>" alt="" width="48" height="48" aria-hidden="true">
            <span class="app-brand-name"><?= e($appName) ?></span>
        </a>

        <nav class="lang-switch" aria-label="<?= e(__('common.language')) ?>">
            <a href="/idioma?lang=es&next=<?= e(rawurlencode($currentUri)) ?>"
               class="<?= $lang === 'es' ? 'active' : '' ?>">ES</a>
            <span aria-hidden="true">|</span>
            <a href="/idioma?lang=en&next=<?= e(rawurlencode($currentUri)) ?>"
               class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
        </nav>
    </header>

    <main class="onboarding-main">
        <div class="onboarding-progress" role="progressbar" aria-valuenow="<?= $stepIndex ?>"
             aria-valuemin="1" aria-valuemax="<?= $stepTotal ?>">
            <div class="onboarding-progress-head">
                <span><?= e(__('onboarding.progress', [':current' => (string) $stepIndex, ':total' => (string) $stepTotal])) ?></span>
                <span class="onboarding-progress-step"><?= e(__('onboarding.steps.' . ($stepKey ?? 'tutorial'))) ?></span>
            </div>
            <div class="progress" style="height: .4rem;">
                <div class="progress-bar" style="width: <?= $progress ?>%"></div>
            </div>
        </div>

        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= e(__($flash['key'], $flash['params'])) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e(__('common.actions.close')) ?>"></button>
            </div>
        <?php endforeach; ?>

        <?= $content ?>
    </main>

    <script>
        window.CauceI18n = <?= json_encode(
            $jsI18n ?? [],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        ) ?>;
    </script>
    <script src="<?= asset_versioned('js/app.min.js') ?>"></script>
</body>
</html>
