<?php

use Cauce\Helpers\Flash;
use Cauce\Services\ThemeService;

$appName = $config['name'] ?? 'Cauce';
$pageTitle = $title ?? $appName;
$lang = $lang ?? 'es';
$theme = $theme ?? (new ThemeService())->resolve($currentUser ?? null);
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$backUrl = $backUrl ?? '/inicio';
$step = (int) ($step ?? 1);
$stepTotal = max(1, (int) ($stepTotal ?? 2));
$flashes = Flash::pull();
?>
<!doctype html>
<html lang="<?= e($lang) ?>" <?= theme_attributes($theme) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e($appName) ?></title>
    <?= partial('partials/theme-boot', ['theme' => $theme]) ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset_versioned('img/favicon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= asset_versioned('img/favicon-64.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset_versioned('img/apple-touch-icon.png') ?>">
    <link rel="stylesheet" href="<?= asset_versioned('icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_versioned('css/app.min.css') ?>">
</head>
<body class="flow-shell">
    <header class="flow-header">
        <div class="d-flex align-items-center gap-2">
            <a class="icon-btn" href="<?= e($backUrl) ?>" aria-label="<?= e(__('common.actions.back')) ?>">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
            </a>
            <span class="fw-semibold"><?= e($pageTitle) ?></span>
        </div>

        <div class="d-flex align-items-center gap-3">
            <?php if ($stepTotal > 1): ?>
                <span class="small text-secondary">
                    <?= e(__('income.progress', [':current' => (string) $step, ':total' => (string) $stepTotal])) ?>
                </span>
            <?php endif; ?>

            <nav class="lang-switch" aria-label="<?= e(__('common.language')) ?>">
                <a href="/idioma?lang=es&next=<?= e(rawurlencode($currentUri)) ?>"
                   class="<?= $lang === 'es' ? 'active' : '' ?>">ES</a>
                <span aria-hidden="true">|</span>
                <a href="/idioma?lang=en&next=<?= e(rawurlencode($currentUri)) ?>"
                   class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
            </nav>
        </div>
    </header>

    <main class="flow-main">
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
