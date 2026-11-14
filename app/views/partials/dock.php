<?php

use Cauce\Services\Navigation;

$currentPath = $currentPath ?? current_path();
$dock = (array) config('navigation.dock', []);
$menuActive = false;
?>
<nav class="app-dock d-xl-none" aria-label="<?= e(__('nav.main_menu')) ?>">
    <?php foreach ($dock as $item): ?>
        <?php $active = Navigation::isActive((string) $item['route'], $currentPath); ?>
        <a class="app-dock-link <?= $active ? 'is-active' : '' ?>" href="<?= e((string) $item['route']) ?>"
           <?= $active ? 'aria-current="page"' : '' ?>>
            <i class="bi <?= e((string) $item['icon']) ?>" aria-hidden="true"></i>
            <span><?= e(__((string) $item['label'])) ?></span>
        </a>
    <?php endforeach; ?>

    <button class="app-dock-link" type="button" data-bs-toggle="offcanvas" data-bs-target="#appDrawer"
            aria-controls="appDrawer">
        <i class="bi bi-grid" aria-hidden="true"></i>
        <span><?= e(__('nav.menu')) ?></span>
    </button>
</nav>
