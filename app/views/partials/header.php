<?php

$currentUser = $currentUser ?? null;
$isAdmin = (bool) ($isAdmin ?? false);
$alias = (string) ($currentUser['alias'] ?? '');
$initial = $alias !== '' ? mb_strtoupper(mb_substr($alias, 0, 1)) : '?';
$fabItems = (array) config('navigation.fab', []);
?>
<header class="app-header">
    <div class="app-header-left">
        <button class="icon-btn" type="button" data-action="sidebar-toggle"
                aria-label="<?= e(__('nav.toggle_menu')) ?>" aria-controls="appSidebar">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <span class="app-header-title"><?= e($title ?? '') ?></span>
    </div>

    <div class="app-header-right">
        <div class="dropdown d-none d-xl-flex">
            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown"
                    aria-expanded="false">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('nav.registrar')) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <?php foreach ($fabItems as $item): ?>
                    <li>
                        <a class="dropdown-item" href="<?= e((string) $item['route']) ?>">
                            <i class="bi <?= e((string) $item['icon']) ?> me-2" aria-hidden="true"></i>
                            <?= e(__((string) $item['label'])) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <a class="icon-btn" href="/buscar" aria-label="<?= e(__('search.title')) ?>"
           title="<?= e(__('search.title')) ?>">
            <i class="bi bi-search" aria-hidden="true"></i>
        </a>

        <a class="icon-btn app-bell" href="/notificaciones" aria-label="<?= e(__('nav.notificaciones')) ?>"
           title="<?= e(__('nav.notificaciones')) ?>">
            <i class="bi bi-bell" aria-hidden="true"></i>
            <?php if (($unreadNotifications ?? 0) > 0): ?>
                <span class="app-bell-badge"><?= (int) min(99, $unreadNotifications) ?></span>
            <?php endif; ?>
        </a>

        <button class="icon-btn" type="button" data-action="toggle-discreto" aria-pressed="false"
                aria-label="<?= e(__('nav.discreto')) ?>" title="<?= e(__('nav.discreto')) ?>">
            <i class="bi bi-eye" data-discreto-icon aria-hidden="true"></i>
        </button>

        <a class="app-avatar" href="/ajustes/perfil" aria-label="<?= e(__('nav.perfil')) ?>"
           title="<?= e($alias) ?>">
            <?= e($initial) ?>
        </a>
    </div>
</header>
