<?php

use Cauce\Helpers\Flash;
use Cauce\Services\Navigation;
use Cauce\Services\ThemeService;

$appName = $config['name'] ?? 'Cauce';
$appVersion = $config['version'] ?? '0.0.0';
$pageTitle = $title ?? $appName;
$lang = $lang ?? 'es';
$currentUser = $currentUser ?? null;
$currentPath = current_path();
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$theme = $theme ?? (new ThemeService())->resolve($currentUser);
$groups = Navigation::groups($currentUser, $currentPath);
$flashes = Flash::pull();
$isAdmin = $currentUser !== null && ($currentUser['rol'] ?? 'user') === 'admin';
?>
<!doctype html>
<html lang="<?= e($lang) ?>" <?= theme_attributes($theme) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(__('common.tagline')) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · <?= e($appName) ?></title>
    <?= partial('partials/theme-boot', ['theme' => $theme]) ?>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset_versioned('img/favicon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= asset_versioned('img/favicon-64.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset_versioned('img/apple-touch-icon.png') ?>">
    <link rel="stylesheet" href="<?= asset_versioned('icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_versioned('css/app.min.css') ?>">
</head>
<body class="app-shell">
    <a class="skip-link" href="#app-main"><?= e(__('nav.skip')) ?></a>

    <?= partial('partials/header', [
        'title' => $pageTitle,
        'currentUser' => $currentUser,
        'isAdmin' => $isAdmin,
    ]) ?>

    <div class="app-frame">
        <?= partial('partials/sidebar', [
            'groups' => $groups,
            'appName' => $appName,
            'currentUser' => $currentUser,
        ]) ?>

        <main class="app-main" id="app-main" tabindex="-1">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e(__($flash['key'], $flash['params'])) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e(__('common.actions.close')) ?>"></button>
                </div>
            <?php endforeach; ?>

            <?= $content ?>
        </main>
    </div>

    <?= partial('partials/dock', ['currentPath' => $currentPath]) ?>
    <?= partial('partials/drawer', [
        'groups' => $groups,
        'appName' => $appName,
        'currentUser' => $currentUser,
        'isAdmin' => $isAdmin,
    ]) ?>
    <?= partial('partials/fab') ?>

    <?= partial('partials/celebration', [
        'pendingCelebration' => $pendingCelebration ?? null,
        'gamificationEnabled' => $gamificationEnabled ?? true,
        'currentUser' => $currentUser,
    ]) ?>

    <?php $moneyFx = \Cauce\Services\MoneyFxService::pull(); ?>
    <?php if ($moneyFx !== null): ?>
        <div hidden data-money-fx="<?= e($moneyFx['kind']) ?>"
             data-level="<?= (int) $moneyFx['level'] ?>"
             data-label="<?= e($moneyFx['label']) ?>"
             data-message="<?= e(__('moneyfx.' . $moneyFx['kind'] . ($moneyFx['level'] >= 4 ? '_big' : ''))) ?>"
             data-close="<?= e(__('common.actions.close')) ?>"></div>
    <?php endif; ?>

    <script>
        window.CauceI18n = <?= json_encode(
            $jsI18n ?? [],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        ) ?>;
        window.CauceUser = <?= json_encode([
            'alias' => (string) ($currentUser['alias'] ?? ''),
            'idioma' => $lang,
            'onboarding' => (int) ($currentUser['onboarding_completado'] ?? 0),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="<?= asset_versioned('js/app.min.js') ?>"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function () {
                    // La app sigue funcionando sin service worker.
                });
            });
        }
    </script>
</body>
</html>
