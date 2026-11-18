<?php
/**
 * Animación de bienvenida al iniciar sesión: el disco de una caja fuerte
 * marca la combinación, hace clic y se abre (3 s). La encola
 * AuthService::login() y la anima assets/js/09-login-fx.js; los estilos están
 * en assets/scss/_login-fx.scss.
 *
 * Disco vectorial propio: anillo exterior, bisel, carátula con 100 marcas y
 * números 0–90, perilla estriada y el índice fijo arriba.
 *
 * @var string $alias
 */
$alias = trim((string) ($alias ?? ''));
$cx = 300;
$cy = 300;
$ticks = '';

for ($i = 0; $i < 100; $i++) {
    $long = $i % 5 === 0;
    $outer = 232;
    $inner = $long ? 214 : 221;
    $angle = deg2rad($i * 3.6 - 90);
    $ticks .= sprintf(
        '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f"%s/>',
        $cx + $outer * cos($angle),
        $cy + $outer * sin($angle),
        $cx + $inner * cos($angle),
        $cy + $inner * sin($angle),
        $long ? ' class="is-long"' : ''
    );
}

$numbers = '';

for ($n = 0; $n < 100; $n += 10) {
    $numbers .= sprintf(
        '<text x="300" y="112" transform="rotate(%d 300 300)">%d</text>',
        (int) round($n * 3.6),
        $n
    );
}

// Estrías de la perilla: 72 dientes alrededor.
$teeth = '';

for ($i = 0; $i < 72; $i++) {
    $a1 = deg2rad($i * 5 - 1.6);
    $a2 = deg2rad($i * 5 + 1.6);
    $teeth .= sprintf(
        'M%.2f %.2f L%.2f %.2f L%.2f %.2f L%.2f %.2f Z ',
        $cx + 128 * cos($a1), $cy + 128 * sin($a1),
        $cx + 138 * cos($a1 + .012), $cy + 138 * sin($a1 + .012),
        $cx + 138 * cos($a2 - .012), $cy + 138 * sin($a2 - .012),
        $cx + 128 * cos($a2), $cy + 128 * sin($a2)
    );
}

// Cepillado radial de la perilla: cuñas con brillo alterno (imita el metal).
$brush = '';

for ($i = 0; $i < 24; $i++) {
    $a1 = deg2rad($i * 15);
    $a2 = deg2rad($i * 15 + 15);
    $brush .= sprintf(
        '<path d="M300 300 L%.2f %.2f A128 128 0 0 1 %.2f %.2f Z" opacity="%.2f"/>',
        $cx + 128 * cos($a1), $cy + 128 * sin($a1),
        $cx + 128 * cos($a2), $cy + 128 * sin($a2),
        0.05 + 0.22 * abs(sin($i * 15 * M_PI / 180 * 2))
    );
}
?>
<div class="login-fx" data-login-fx role="status" aria-live="polite"
     aria-label="<?= e(__('auth.login_fx.label')) ?>">
    <div class="login-fx-stage">
        <svg class="login-fx-dial" viewBox="0 0 600 600" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="lfx-rim" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#f4f5f7"/>
                    <stop offset=".45" stop-color="#c9ccd1"/>
                    <stop offset=".55" stop-color="#e8eaed"/>
                    <stop offset="1" stop-color="#9ea2a8"/>
                </linearGradient>
                <linearGradient id="lfx-bezel" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#8d9197"/>
                    <stop offset=".5" stop-color="#4b4f55"/>
                    <stop offset="1" stop-color="#a7abb1"/>
                </linearGradient>
                <radialGradient id="lfx-face" cx=".5" cy=".42" r=".62">
                    <stop offset="0" stop-color="#eef0f2"/>
                    <stop offset=".7" stop-color="#c4c8cd"/>
                    <stop offset="1" stop-color="#9da1a7"/>
                </radialGradient>
                <linearGradient id="lfx-face-sheen" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#fff" stop-opacity=".55"/>
                    <stop offset=".5" stop-color="#fff" stop-opacity="0"/>
                    <stop offset="1" stop-color="#000" stop-opacity=".12"/>
                </linearGradient>
                <radialGradient id="lfx-knob" cx=".5" cy=".5" r=".5">
                    <stop offset="0" stop-color="#e9ebee"/>
                    <stop offset="1" stop-color="#b4b8be"/>
                </radialGradient>
                <linearGradient id="lfx-teeth" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#d9dce0"/>
                    <stop offset="1" stop-color="#7d8187"/>
                </linearGradient>
            </defs>

            <!-- Fijo: anillo exterior, bisel y el índice de arriba. -->
            <circle cx="300" cy="300" r="292" fill="url(#lfx-rim)"/>
            <circle cx="300" cy="300" r="292" fill="none" stroke="#8e9298" stroke-width="2"/>
            <circle cx="300" cy="300" r="252" fill="url(#lfx-bezel)"/>
            <line class="login-fx-index" x1="300" y1="6" x2="300" y2="58"/>

            <!-- Gira: carátula con marcas y números, y la perilla. -->
            <g class="login-fx-rotor">
                <circle cx="300" cy="300" r="243" fill="url(#lfx-face)"/>
                <circle cx="300" cy="300" r="243" fill="url(#lfx-face-sheen)"/>
                <g class="login-fx-ticks"><?= $ticks ?></g>
                <g class="login-fx-numbers"><?= $numbers ?></g>
                <circle cx="300" cy="300" r="142" fill="#8f9399" opacity=".55"/>
                <path d="<?= $teeth ?>" fill="url(#lfx-teeth)"/>
                <circle cx="300" cy="300" r="129" fill="url(#lfx-knob)"/>
                <g class="login-fx-brush" fill="#fff"><?= $brush ?></g>
                <circle cx="300" cy="300" r="129" fill="none" stroke="#9a9ea4" stroke-width="1.5"/>
                <circle cx="300" cy="300" r="12" fill="#c7cace" stroke="#9a9ea4" stroke-width="1"/>
            </g>

            <!-- Destello verde al abrir. -->
            <circle class="login-fx-glow" cx="300" cy="300" r="250"/>
        </svg>
        <span class="login-fx-badge" aria-hidden="true"><i class="bi bi-unlock-fill"></i></span>
    </div>
    <p class="login-fx-title">
        <?= e($alias !== '' ? __('auth.login_fx.hello', [':name' => $alias]) : __('auth.login_fx.hello_anon')) ?>
    </p>
    <p class="login-fx-subtitle"><?= e(__('auth.login_fx.subtitle')) ?></p>
</div>
