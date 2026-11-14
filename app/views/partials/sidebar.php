<?php

$currentUser = $currentUser ?? null;
$appName = $appName ?? 'Cauce';
?>
<aside class="app-sidebar d-none d-xl-flex" id="appSidebar">
    <a class="app-sidebar-brand" href="/inicio">
        <img class="brand-icon" src="<?= asset_versioned('img/icon-192.png') ?>" alt="" width="32" height="32" aria-hidden="true">
        <span class="app-sidebar-brand-name"><?= e($appName) ?></span>
    </a>

    <nav class="app-nav" aria-label="<?= e(__('nav.main_menu')) ?>">
        <?= partial('partials/nav', ['groups' => $groups ?? []]) ?>
    </nav>

    <div class="app-sidebar-footer">
        <a class="app-nav-link" href="/ajustes/perfil">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <span class="app-nav-text"><?= e((string) ($currentUser['alias'] ?? '')) ?></span>
        </a>
        <form method="post" action="/logout" class="m-0">
            <?= csrf_field() ?>
            <button type="submit" class="app-nav-link app-nav-button">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                <span class="app-nav-text"><?= e(__('common.actions.logout')) ?></span>
            </button>
        </form>
    </div>
</aside>
