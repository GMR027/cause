<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\WishlistService;
use PHPUnit\Framework\TestCase;

final class WishlistServiceTest extends TestCase
{
    public function test_impacto_de_compra(): void
    {
        $impact = WishlistService::impact(100000, 25000);

        self::assertSame(100000, $impact['before']);
        self::assertSame(75000, $impact['after']);
        self::assertSame(25000, $impact['amount']);
        self::assertSame(25.0, $impact['percent']);
        self::assertFalse($impact['exceeds']);
    }

    public function test_impacto_no_baja_de_cero(): void
    {
        $impact = WishlistService::impact(10000, 25000);

        self::assertSame(0, $impact['after']);
        self::assertTrue($impact['exceeds']);
    }

    public function test_impacto_sin_ahorro(): void
    {
        $impact = WishlistService::impact(0, 10000);

        self::assertSame(0, $impact['after']);
        self::assertSame(0.0, $impact['percent']);
        self::assertTrue($impact['exceeds']);
    }

    public function test_enfriamiento_activo(): void
    {
        self::assertTrue(WishlistService::coolingActive('2026-11-20', '2026-11-13'));
        self::assertTrue(WishlistService::coolingActive('2026-11-13', '2026-11-13'));
        self::assertFalse(WishlistService::coolingActive('2026-11-12', '2026-11-13'));
        self::assertFalse(WishlistService::coolingActive(null, '2026-11-13'));
        self::assertFalse(WishlistService::coolingActive('', '2026-11-13'));
    }
}
