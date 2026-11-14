<?php

$currentUser = $currentUser ?? null;
$appName = $appName ?? 'Cauce';
$lang = $lang ?? 'es';
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<div class="offcanvas offcanvas-start app-drawer" tabindex="-1" id="appDrawer"
     aria-labelledby="appDrawerLabel">
    <div class="offcanvas-header">
        <a href="/inicio" class="app-brand" id="appDrawerLabel">
            <img class="brand-icon" src="<?= asset_versioned('img/icon-192.png') ?>" alt="" width="32" height="32" aria-hidden="true">
            <span class="app-brand-name"><?= e($appName) ?></span>
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column">
        <nav class="app-nav" aria-label="<?= e(__('nav.main_menu')) ?>">
            <?= partial('partials/nav', ['groups' => $groups ?? []]) ?>
        </nav>

        <div class="app-drawer-footer mt-auto pt-3">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                <nav class="lang-switch" aria-label="<?= e(__('common.language')) ?>">
                    <a href="/idioma?lang=es&next=<?= e(rawurlencode($currentUri)) ?>"
                       class="<?= $lang === 'es' ? 'active' : '' ?>">ES</a>
                    <span aria-hidden="true">|</span>
                    <a href="/idioma?lang=en&next=<?= e(rawurlencode($currentUri)) ?>"
                       class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
                </nav>

                <a href="/ajustes/perfil" class="small text-decoration-none">
                    <?= e((string) ($currentUser['alias'] ?? '')) ?>
                </a>
            </div>

            <form method="post" action="/logout" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger w-100">
                    <?= e(__('common.actions.logout')) ?>
                </button>
            </form>
        </div>
    </div>
</div>
