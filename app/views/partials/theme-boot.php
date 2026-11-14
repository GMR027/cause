<?php
/**
 * Tema compartido por todos los layouts.
 *
 * Aplica el tema guardado antes del primer pintado para evitar destellos y
 * resuelve el modo automático según la preferencia del sistema.
 */
$theme = $theme ?? [];
$themeColor = (string) ($theme['color'] ?? '#157f6b');
?>
<meta name="theme-color" content="<?= e($themeColor) ?>">
<script>
    window.CauceServerTheme = <?= json_encode(
        $theme,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    ) ?>;
</script>
<script>
    (function () {
        var root = document.documentElement;

        try {
            if (sessionStorage.getItem('cauce_discreto') === '1') {
                root.setAttribute('data-discreto', '1');
            }
        } catch (e) {}

        var stored = window.CauceServerTheme;

        if (!stored) {
            try {
                stored = JSON.parse(localStorage.getItem('cauce_theme') || 'null');
            } catch (e) {
                stored = null;
            }
        }

        if (!stored) {
            return;
        }

        var color = /^#[0-9a-f]{6}$/i.test(stored.color || '') ? stored.color : '#157f6b';
        var mode = stored.modo || 'auto';
        var dark = mode === 'oscuro'
            || (mode === 'auto' && window.matchMedia
                && window.matchMedia('(prefers-color-scheme: dark)').matches);
        var r = parseInt(color.substr(1, 2), 16);
        var g = parseInt(color.substr(3, 2), 16);
        var b = parseInt(color.substr(5, 2), 16);
        var shade = function (factor) {
            return '#' + [r, g, b].map(function (value) {
                return Math.round(value * factor).toString(16).padStart(2, '0');
            }).join('');
        };

        root.setAttribute('data-cauce-modo', mode);
        root.setAttribute('data-enfoque', stored.enfoque || 'mixto');
        root.setAttribute('data-fondo', stored.fondo || 'ninguno');
        root.setAttribute('data-textura', stored.textura || 'dots');
        root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        root.style.setProperty('--cauce-primary', color);
        root.style.setProperty('--cauce-primary-rgb', r + ', ' + g + ', ' + b);
        root.style.setProperty('--cauce-primary-hover', shade(0.88));
        root.style.setProperty('--cauce-primary-active', shade(0.78));
        root.style.setProperty('--cauce-primary-soft', 'rgba(' + r + ', ' + g + ', ' + b + ', 0.12)');

        var meta = document.querySelector('meta[name="theme-color"]');

        if (meta) {
            meta.setAttribute('content', dark ? '#0d1416' : color);
        }
    })();
</script>
