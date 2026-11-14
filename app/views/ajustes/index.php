<?php

$sections = [
    ['route' => '/ajustes/perfil', 'icon' => 'bi-person-circle', 'label' => 'settings.sections.perfil', 'desc' => 'settings.sections.perfil_desc'],
    ['route' => '/ajustes/ingreso', 'icon' => 'bi-cash-coin', 'label' => 'settings.sections.ingreso', 'desc' => 'settings.sections.ingreso_desc'],
    ['route' => '/ajustes/recurrentes', 'icon' => 'bi-arrow-repeat', 'label' => 'settings.sections.recurrentes', 'desc' => 'settings.sections.recurrentes_desc'],
    ['route' => '/ajustes/notificaciones', 'icon' => 'bi-bell', 'label' => 'settings.sections.notificaciones', 'desc' => 'settings.sections.notificaciones_desc'],
    ['route' => '/ajustes/plantillas', 'icon' => 'bi-lightning-charge', 'label' => 'settings.sections.plantillas', 'desc' => 'settings.sections.plantillas_desc'],
    ['route' => '/ajustes/tags', 'icon' => 'bi-tags', 'label' => 'settings.sections.tags', 'desc' => 'settings.sections.tags_desc'],
    ['route' => '/ajustes/temas', 'icon' => 'bi-palette', 'label' => 'settings.sections.temas', 'desc' => 'settings.sections.temas_desc'],
    ['route' => '/ajustes/seguridad', 'icon' => 'bi-shield-lock', 'label' => 'settings.sections.seguridad', 'desc' => 'settings.sections.seguridad_desc'],
    ['route' => '/ajustes/compartir', 'icon' => 'bi-qr-code', 'label' => 'settings.sections.compartir', 'desc' => 'settings.sections.compartir_desc'],
    ['route' => '/ajustes/android', 'icon' => 'bi-android2', 'label' => 'settings.sections.android', 'desc' => 'settings.sections.android_desc'],
    ['route' => '/ajustes/privacidad', 'icon' => 'bi-incognito', 'label' => 'settings.sections.privacidad', 'desc' => 'settings.sections.privacidad_desc'],
    ['route' => '/subcategorias', 'icon' => 'bi-diagram-3', 'label' => 'settings.sections.subcategorias', 'desc' => 'settings.sections.subcategorias_desc'],
    ['route' => '/soporte', 'icon' => 'bi-life-preserver', 'label' => 'settings.sections.soporte', 'desc' => 'settings.sections.soporte_desc'],
    ['route' => '/acerca-de', 'icon' => 'bi-info-circle', 'label' => 'settings.sections.acerca', 'desc' => 'settings.sections.acerca_desc'],
];
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('settings.index.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('settings.index.subtitle')) ?></p>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($sections as $section): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <a class="settings-link" href="<?= e($section['route']) ?>">
                <span class="settings-icon" aria-hidden="true">
                    <i class="bi <?= e($section['icon']) ?>"></i>
                </span>
                <span class="settings-text">
                    <span class="fw-semibold"><?= e(__($section['label'])) ?></span>
                    <span class="small text-secondary"><?= e(__($section['desc'])) ?></span>
                </span>
                <i class="bi bi-chevron-right ms-auto" aria-hidden="true"></i>
            </a>
        </div>
    <?php endforeach; ?>
</div>
