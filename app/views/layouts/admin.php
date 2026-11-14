<?php

use Cauce\Helpers\Flash;

$appName = $config['name'] ?? 'Cauce';
$appVersion = $config['version'] ?? '0.0.0';
$pageTitle = $title ?? $appName;
$lang = $lang ?? 'es';
$currentUser = $currentUser ?? null;
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
    <link rel="stylesheet" href="<?= asset_versioned('css/app.min.css') ?>">
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="border-bottom app-chrome">
        <div class="container py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-3">
                <a href="/admin" class="d-flex align-items-center gap-2 text-decoration-none">
                    <img class="brand-icon" src="<?= asset_versioned('img/icon-192.png') ?>" alt="" width="32" height="32" aria-hidden="true">
                    <span class="fw-semibold"><?= e($appName) ?></span>
                </a>
                <span class="badge phase-badge"><?= e(__('common.admin.title')) ?></span>
            </div>

            <div class="d-flex align-items-center gap-2">
                <nav class="lang-switch" aria-label="<?= e(__('common.language')) ?>">
                    <a href="/idioma?lang=es&next=<?= e(rawurlencode($currentUri)) ?>"
                       class="<?= $lang === 'es' ? 'active' : '' ?>">ES</a>
                    <span aria-hidden="true">|</span>
                    <a href="/idioma?lang=en&next=<?= e(rawurlencode($currentUri)) ?>"
                       class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
                </nav>

                <a href="/admin/usuarios" class="btn btn-sm btn-outline-secondary"><?= e(__('common.admin.users')) ?></a>
                <a href="/admin/usuarios/crear" class="btn btn-sm btn-outline-secondary"><?= e(__('common.admin.create_user')) ?></a>
                <a href="/admin/auditoria" class="btn btn-sm btn-outline-secondary"><?= e(__('common.admin.audit')) ?></a>
                <a href="/" class="btn btn-sm btn-outline-secondary"><?= e(__('common.admin.back_to_site')) ?></a>

                <form method="post" action="/logout" class="m-0">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('common.actions.logout')) ?></button>
                </form>
            </div>
        </div>
    </header>

    <main class="flex-grow-1 py-4">
        <div class="container">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e(__($flash['key'], $flash['params'])) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e(__('common.actions.close')) ?>"></button>
                </div>
            <?php endforeach; ?>

            <?= $content ?>
        </div>
    </main>

    <footer class="border-top app-chrome py-3 mt-auto">
        <div class="container d-flex flex-wrap justify-content-between gap-2 small text-secondary">
            <span>&copy; <?= date('Y') ?> <?= e($appName) ?> · v<?= e($appVersion) ?></span>
            <span>PHP <?= e(PHP_VERSION) ?> · MySQL</span>
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
