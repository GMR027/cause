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
}
