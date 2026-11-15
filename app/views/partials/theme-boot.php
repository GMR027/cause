<?php
/**
 * Tema compartido por todos los layouts.
 *
 * Aplica el tema guardado antes del primer pintado para evitar destellos y
 * resuelve el modo automático según la preferencia del sistema.
 */
$theme = $theme ?? [];
$themeColor = (string) ($theme['color'] ?? '#157f6b');
$doodles = (array) config('doodles', []);
?>
<meta name="theme-color" content="<?= e($themeColor) ?>">
<script>
    window.CauceServerTheme = <?= json_encode(
        $theme,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    ) ?>;
</script>
<script>
    // Texturas con garabatos financieros: el SVG se tiñe con el color del tema.
    window.CauceDoodles = {
        tiles: <?= json_encode($doodles, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>,
        apply: function (element, color, alpha) {
            var tiles = this.tiles;
            var opacity = typeof alpha === 'number' && alpha > 0 ? String(alpha) : '0.3';

            element.style.setProperty('--cauce-texture-scale', String(parseFloat(opacity) / 0.3));

            Object.keys(tiles).forEach(function (name) {
                var svg = tiles[name].split('__C__').join(color).split('__A__').join(opacity);

                element.style.setProperty(
                    '--cauce-doodle-' + name,
                    'url("data:image/svg+xml,' + encodeURIComponent(svg) + '")'
                );
            });
        }
    };
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
            window.CauceDoodles.apply(root, '#157f6b');

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
        var opacidad = parseInt(stored.opacidad, 10);
        window.CauceDoodles.apply(root, color, (isNaN(opacidad) ? 30 : Math.min(60, Math.max(5, opacidad))) / 100);

        var meta = document.querySelector('meta[name="theme-color"]');

        if (meta) {
            meta.setAttribute('content', dark ? '#0d1416' : color);
        }
    })();
</script>
