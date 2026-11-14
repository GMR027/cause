<?php

$groups = $groups ?? [];
?>
<?php foreach ($groups as $group): ?>
    <div class="app-nav-group">
        <?php if (!empty($group['label'])): ?>
            <div class="app-nav-label"><?= e(__((string) $group['label'])) ?></div>
        <?php endif; ?>

        <?php foreach ((array) $group['items'] as $item): ?>
            <a class="app-nav-link <?= !empty($item['active']) ? 'is-active' : '' ?> <?= !empty($item['desktop_only']) ? 'desktop-only' : '' ?>"
               href="<?= e((string) $item['route']) ?>"
               <?= !empty($item['active']) ? 'aria-current="page"' : '' ?>>
                <i class="bi <?= e((string) $item['icon']) ?>" aria-hidden="true"></i>
                <span class="app-nav-text"><?= e(__((string) $item['label'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
