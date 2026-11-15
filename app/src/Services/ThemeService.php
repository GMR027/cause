<?php

declare(strict_types=1);

namespace Cauce\Services;

/**
 * Validación y derivación del tema visual.
 *
 * El color principal se valida con la fórmula de luminancia relativa de
 * WCAG 2.1: el texto blanco sobre el color debe alcanzar 4.5:1.
 */
final class ThemeService
{
    /**
     * @return array{modo: string, color: string, enfoque: string, fondo: string, textura: string, opacidad: int}
     */
    public function defaults(): array
    {
        $defaults = config('themes.defaults', []);

        return [
            'modo' => (string) ($defaults['modo'] ?? 'auto'),
            'color' => strtolower((string) ($defaults['color'] ?? '#157f6b')),
            'enfoque' => (string) ($defaults['enfoque'] ?? 'mixto'),
            'fondo' => (string) ($defaults['fondo'] ?? 'ninguno'),
            'textura' => (string) ($defaults['textura'] ?? 'dots'),
            'opacidad' => (int) ($defaults['opacidad'] ?? 30),
        ];
    }

    /**
     * Opacidad de textura dentro del rango permitido.
     */
    public function clampOpacity(mixed $value, int $fallback): int
    {
        if (!is_numeric($value)) {
            return $fallback;
        }

        $range = (array) config('themes.texture_opacity', []);
        $min = (int) ($range['min'] ?? 5);
        $max = (int) ($range['max'] ?? 60);

        return max($min, min($max, (int) round((float) $value)));
    }

    /**
     * @return list<string>
     */
    public function palette(): array
    {
        $palette = config('themes.palette', []);

        return is_array($palette) ? array_values(array_filter($palette, 'is_string')) : [];
    }

    /**
     * @param array<string, mixed>|null $user
     * @return array{modo: string, color: string, enfoque: string, fondo: string, textura: string, opacidad: int}
     */
    public function resolve(?array $user): array
    {
        $theme = $this->defaults();

        if ($user === null) {
            return $theme;
        }

        $theme['modo'] = $this->pick((string) ($user['tema_modo'] ?? ''), (array) config('themes.modes', []), $theme['modo']);
        $theme['enfoque'] = $this->pick((string) ($user['tema_enfoque'] ?? ''), (array) config('themes.focus', []), $theme['enfoque']);
        $theme['fondo'] = $this->pick((string) ($user['tema_efecto_fondo'] ?? ''), (array) config('themes.backgrounds', []), $theme['fondo']);
        $theme['textura'] = $this->pick((string) ($user['tema_textura'] ?? ''), (array) config('themes.textures', []), $theme['textura']);
        $theme['opacidad'] = $this->clampOpacity($user['tema_textura_opacidad'] ?? null, $theme['opacidad']);

        $color = strtolower((string) ($user['tema_color_principal'] ?? ''));

        if ($this->isValidHex($color)) {
            $theme['color'] = $color;
        }

        return $theme;
    }

    /**
     * Valida la entrada del formulario. No persiste nada.
     *
     * @param array<string, mixed> $input
     * @return array{theme: array<string, string>, errors: list<array{key: string, params: array<string, string>}>}
     */
    public function validate(array $input): array
    {
        $theme = $this->defaults();
        $errors = [];

        $modo = (string) ($input['modo'] ?? '');
        if (!in_array($modo, (array) config('themes.modes', []), true)) {
            $errors[] = ['key' => 'themes.errors.mode', 'params' => []];
        } else {
            $theme['modo'] = $modo;
        }

        $color = strtolower(trim((string) ($input['color'] ?? '')));

        if (!$this->isValidHex($color)) {
            $errors[] = ['key' => 'themes.errors.color_format', 'params' => []];
        } elseif (!$this->isAccessible($color)) {
            $errors[] = [
                'key' => 'themes.errors.contrast',
                'params' => [
                    ':ratio' => number_format($this->contrastRatio($color, '#ffffff'), 2),
                    ':min' => (string) config('themes.min_contrast', 4.5),
                    ':suggestion' => $this->suggest($color),
                ],
            ];
        } else {
            $theme['color'] = $color;
        }

        $enfoque = (string) ($input['enfoque'] ?? '');
        if (!in_array($enfoque, (array) config('themes.focus', []), true)) {
            $errors[] = ['key' => 'themes.errors.focus', 'params' => []];
        } else {
            $theme['enfoque'] = $enfoque;
        }

        $fondo = (string) ($input['fondo'] ?? '');
        if (!in_array($fondo, (array) config('themes.backgrounds', []), true)) {
            $errors[] = ['key' => 'themes.errors.background', 'params' => []];
        } else {
            $theme['fondo'] = $fondo;
        }

        $textura = (string) ($input['textura'] ?? '');

        if (in_array($textura, (array) config('themes.textures', []), true)) {
            $theme['textura'] = $textura;
        }

        $theme['opacidad'] = $this->clampOpacity($input['opacidad'] ?? null, $theme['opacidad']);

        return ['theme' => $theme, 'errors' => $errors];
    }

    public function isValidHex(string $color): bool
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $color) === 1;
    }

    public function isAccessible(string $color): bool
    {
        return $this->contrastRatio($color, '#ffffff') >= (float) config('themes.min_contrast', 4.5);
    }

    public function contrastRatio(string $a, string $b): float
    {
        $lighter = max($this->luminance($a), $this->luminance($b));
        $darker = min($this->luminance($a), $this->luminance($b));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public function luminance(string $color): float
    {
        [$r, $g, $b] = $this->rgb($color);

        $linear = static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);
    }

    /**
     * Paleta segura más cercana al color propuesto.
     */
    public function suggest(string $color): string
    {
        [$r, $g, $b] = $this->rgb($color);

        $best = $this->defaults()['color'];
        $bestDistance = PHP_INT_MAX;

        foreach ($this->palette() as $candidate) {
            if (!$this->isAccessible($candidate)) {
                continue;
            }

            [$cr, $cg, $cb] = $this->rgb($candidate);
            $distance = ($r - $cr) ** 2 + ($g - $cg) ** 2 + ($b - $cb) ** 2;

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Valores derivados para hover, active, soft y rgb.
     *
     * @return array{color: string, rgb: string, hover: string, active: string, soft: string}
     */
    public function derive(string $color): array
    {
        [$r, $g, $b] = $this->rgb($color);

        return [
            'color' => $color,
            'rgb' => $r . ', ' . $g . ', ' . $b,
            'hover' => $this->shade($r, $g, $b, 0.12),
            'active' => $this->shade($r, $g, $b, 0.22),
            'soft' => 'rgba(' . $r . ', ' . $g . ', ' . $b . ', 0.12)',
        ];
    }

    public function shade(int $r, int $g, int $b, float $amount): string
    {
        $factor = max(0.0, 1.0 - $amount);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($r * $factor),
            (int) round($g * $factor),
            (int) round($b * $factor)
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public function rgb(string $color): array
    {
        if (!$this->isValidHex($color)) {
            return [0, 0, 0];
        }

        return [
            (int) hexdec(substr($color, 1, 2)),
            (int) hexdec(substr($color, 3, 2)),
            (int) hexdec(substr($color, 5, 2)),
        ];
    }

    /**
     * @param array<mixed> $allowed
     */
    private function pick(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
