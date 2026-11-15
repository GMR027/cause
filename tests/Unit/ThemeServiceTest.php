<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\ThemeService;
use PHPUnit\Framework\TestCase;

final class ThemeServiceTest extends TestCase
{
    private ThemeService $service;

    protected function setUp(): void
    {
        $this->service = new ThemeService();
    }

    public function test_black_and_white_reach_maximum_contrast(): void
    {
        self::assertEqualsWithDelta(21.0, $this->service->contrastRatio('#000000', '#ffffff'), 0.01);
    }

    public function test_default_color_meets_wcag(): void
    {
        self::assertTrue($this->service->isAccessible('#157f6b'));
    }

    public function test_yellow_fails_wcag(): void
    {
        self::assertFalse($this->service->isAccessible('#ffff00'));
    }

    public function test_every_palette_color_meets_wcag(): void
    {
        foreach ($this->service->palette() as $color) {
            self::assertTrue($this->service->isAccessible($color), $color . ' no cumple WCAG');
        }
    }

    public function test_suggestion_is_always_accessible(): void
    {
        $suggestion = $this->service->suggest('#ffff00');

        self::assertTrue($this->service->isAccessible($suggestion));
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $suggestion);
    }

    public function test_invalid_hex_returns_error(): void
    {
        $result = $this->service->validate([
            'modo' => 'auto',
            'color' => 'azul',
            'enfoque' => 'mixto',
            'fondo' => 'ninguno',
            'textura' => 'dots',
        ]);

        self::assertNotEmpty($result['errors']);
        self::assertSame('themes.errors.color_format', $result['errors'][0]['key']);
    }

    public function test_low_contrast_returns_suggestion(): void
    {
        $result = $this->service->validate([
            'modo' => 'claro',
            'color' => '#ffff00',
            'enfoque' => 'iconos',
            'fondo' => 'radial',
            'textura' => 'grid',
        ]);

        self::assertNotEmpty($result['errors']);
        self::assertSame('themes.errors.contrast', $result['errors'][0]['key']);
        self::assertTrue($this->service->isAccessible($result['errors'][0]['params'][':suggestion']));
    }

    public function test_derived_colors_are_darker_than_base(): void
    {
        $derived = $this->service->derive('#157f6b');

        self::assertLessThan(
            $this->service->luminance('#157f6b'),
            $this->service->luminance($derived['hover'])
        );
        self::assertLessThan(
            $this->service->luminance($derived['hover']),
            $this->service->luminance($derived['active'])
        );
    }

    public function test_valid_theme_passes_without_errors(): void
    {
        $result = $this->service->validate([
            'modo' => 'oscuro',
            'color' => '#1565c0',
            'enfoque' => 'botones',
            'fondo' => 'textura',
            'textura' => 'waves',
        ]);

        self::assertSame([], $result['errors']);
        self::assertSame('#1565c0', $result['theme']['color']);
        self::assertSame('waves', $result['theme']['textura']);
    }

    public function test_texture_opacity_is_clamped_to_configured_range(): void
    {
        self::assertSame(5, $this->service->clampOpacity('1', 30));
        self::assertSame(60, $this->service->clampOpacity(95, 30));
        self::assertSame(45, $this->service->clampOpacity('45', 30));
        self::assertSame(30, $this->service->clampOpacity('abc', 30));
        self::assertSame(30, $this->service->clampOpacity(null, 30));
    }

    public function test_validate_keeps_opacity_and_new_textures(): void
    {
        $result = $this->service->validate([
            'modo' => 'auto',
            'color' => '#157f6b',
            'enfoque' => 'mixto',
            'fondo' => 'textura',
            'textura' => 'cerdito',
            'opacidad' => '20',
        ]);

        self::assertSame([], $result['errors']);
        self::assertSame('cerdito', $result['theme']['textura']);
        self::assertSame(20, $result['theme']['opacidad']);
    }

    public function test_every_texture_has_a_doodle_or_css_pattern(): void
    {
        $doodles = array_keys((array) require __DIR__ . '/../../app/config/doodles.php');

        foreach ((array) config('themes.textures', []) as $texture) {
            self::assertTrue(
                in_array($texture, ['dots', 'grid', 'waves'], true) || in_array($texture, $doodles, true),
                'Textura sin mosaico: ' . $texture
            );
        }
    }
}
