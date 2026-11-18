<?php

use Cauce\Helpers\Flash;

$appName = $config['name'] ?? 'Cauce';
$appVersion = $config['version'] ?? '0.0.0';
$pageTitle = $title ?? $appName;
$lang = $lang ?? 'es';
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$flashes = Flash::pull();
?>
<!doctype html>
<html lang="<?= e($lang) ?>" <?= theme_attributes($theme ?? []) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(__('common.tagline')) ?>">
    <title><?= e($pageTitle) ?> · <?= e($appName) ?></title>
    <?= partial('partials/theme-boot', ['theme' => $theme ?? []]) ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset_versioned('img/favicon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= asset_versioned('img/favicon-64.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset_versioned('img/apple-touch-icon.png') ?>">
    <link rel="stylesheet" href="<?= asset_versioned('icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_versioned('css/app.min.css') ?>">
</head>
<body class="auth-page d-flex flex-column min-vh-100">
    <header class="container py-4 d-flex align-items-center justify-content-between">
        <a href="/" class="d-flex align-items-center gap-2 text-decoration-none">
            <img class="brand-icon" src="<?= asset_versioned('img/logo-animated.svg') ?>" alt="" width="48" height="48" aria-hidden="true">
            <span class="fw-semibold"><?= e($appName) ?></span>
        </a>

        <nav class="lang-switch" aria-label="<?= e(__('common.language')) ?>">
            <a href="/idioma?lang=es&next=<?= e(rawurlencode($currentUri)) ?>"
               class="<?= $lang === 'es' ? 'active' : '' ?>">ES</a>
            <span aria-hidden="true">|</span>
            <a href="/idioma?lang=en&next=<?= e(rawurlencode($currentUri)) ?>"
               class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
        </nav>
    </header>

    <main class="flex-grow-1 d-flex align-items-start py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-md-9 col-lg-7 col-xl-6">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                            <?= e(__($flash['key'], $flash['params'])) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e(__('common.actions.close')) ?>"></button>
                        </div>
                    <?php endforeach; ?>

                    <?= $content ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="py-4">
        <div class="container text-center small text-secondary">
            &copy; <?= date('Y') ?> <?= e($appName) ?> · v<?= e($appVersion) ?>
            <span class="d-block mt-2">
                <a href="/privacidad"><?= e(__('legal.titles.privacidad')) ?></a>
                · <a href="/terminos"><?= e(__('legal.titles.terminos')) ?></a>
                · <a href="/aviso-legal"><?= e(__('legal.titles.aviso')) ?></a>
            </span>
        </div>
    </footer>

    <script>
        window.CauceI18n = <?= json_encode(
            $jsI18n ?? [],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        ) ?>;
    </script>
    <script src="<?= asset_versioned('js/app.min.js') ?>"></script>
</body>
</html>
