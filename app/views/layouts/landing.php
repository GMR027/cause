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
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(__('landing.meta_description')) ?>">
    <meta name="theme-color" content="#0b3d36">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset_versioned('img/favicon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= asset_versioned('img/favicon-64.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset_versioned('img/apple-touch-icon.png') ?>">
    <link rel="stylesheet" href="<?= asset_versioned('icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_versioned('css/app.min.css') ?>">
</head>
<body class="landing-shell">
<a class="skip-link" href="#main"><?= e(__('landing.skip')) ?></a>

<header class="landing-header">
    <div class="container landing-header-inner">
        <a class="landing-brand" href="/" aria-label="<?= e($appName) ?>">
            <img class="landing-logo-img" src="<?= asset_versioned('img/logo-animated.svg') ?>" alt="" width="40" height="40" aria-hidden="true">
            <span class="landing-brand-text">
                <strong><?= e($appName) ?></strong>
                <small><?= e(__('landing.brand_tag')) ?></small>
            </span>
        </a>

        <button class="navbar-toggler landing-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#landingNav" aria-controls="landingNav" aria-expanded="false"
                aria-label="<?= e(__('landing.menu')) ?>">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <div class="collapse navbar-collapse landing-nav" id="landingNav">
            <nav class="landing-links" aria-label="<?= e(__('landing.menu')) ?>">
                <a href="#producto"><?= e(__('landing.nav.product')) ?></a>
                <a href="#como-funciona"><?= e(__('landing.nav.how')) ?></a>
                <a href="#metodos"><?= e(__('landing.nav.methods')) ?></a>
                <a href="#privacidad"><?= e(__('landing.nav.privacy')) ?></a>
            </nav>

            <div class="landing-actions">
                <nav class="lang-switch" aria-label="<?= e(__('common.language')) ?>">
                    <a href="/idioma?lang=es&next=<?= e(rawurlencode($currentUri)) ?>"
                       class="<?= $lang === 'es' ? 'active' : '' ?>">ES</a>
                    <span aria-hidden="true">|</span>
                    <a href="/idioma?lang=en&next=<?= e(rawurlencode($currentUri)) ?>"
                       class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
                </nav>

                <?php if ($currentUser !== null): ?>
                    <a href="/inicio" class="btn btn-sm landing-btn-ghost"><?= e(__('landing.nav.dashboard')) ?></a>
                <?php else: ?>
                    <a href="/login" class="btn btn-sm landing-btn-ghost"><?= e(__('landing.nav.login')) ?></a>
                    <a href="/registro" class="btn btn-sm landing-btn-solid"><?= e(__('landing.nav.register')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<main id="main" class="landing-main">
    <?php if ($flashes !== []): ?>
        <div class="container pt-3">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e(__($flash['key'], $flash['params'])) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                            aria-label="<?= e(__('common.actions.close')) ?>"></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $content ?>
</main>

<footer class="landing-footer">
    <div class="container">
        <div class="landing-footer-grid">
            <div class="landing-footer-brand">
                <a class="landing-brand" href="/">
                    <img class="landing-logo-img" src="<?= asset_versioned('img/logo-animated.svg') ?>" alt="" width="40" height="40" aria-hidden="true">
                    <span class="landing-brand-text">
                        <strong><?= e($appName) ?></strong>
                        <small><?= e(__('landing.brand_tag')) ?></small>
                    </span>
                </a>
                <p><?= e(__('landing.footer.tagline')) ?></p>
            </div>

            <div>
                <h2><?= e(__('landing.footer.product')) ?></h2>
                <a href="#producto"><?= e(__('landing.nav.product')) ?></a>
                <a href="#como-funciona"><?= e(__('landing.nav.how')) ?></a>
                <a href="#metodos"><?= e(__('landing.nav.methods')) ?></a>
            </div>

            <div>
                <h2><?= e(__('landing.footer.access')) ?></h2>
                <a href="/login"><?= e(__('landing.nav.login')) ?></a>
                <a href="/registro"><?= e(__('landing.nav.register')) ?></a>
                <a href="/idioma?lang=<?= $lang === 'es' ? 'en' : 'es' ?>"><?= $lang === 'es' ? 'English' : 'Español' ?></a>
            </div>

            <div>
                <h2><?= e(__('landing.footer.legal')) ?></h2>
                <a href="/privacidad"><?= e(__('legal.titles.privacidad')) ?></a>
                <a href="/terminos"><?= e(__('legal.titles.terminos')) ?></a>
                <a href="/aviso-legal"><?= e(__('legal.titles.aviso')) ?></a>
            </div>
        </div>

        <div class="landing-footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= e($appName) ?> · v<?= e($appVersion) ?></span>
            <span><?= e(__('landing.footer.built')) ?></span>
        </div>
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
