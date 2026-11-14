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
    public function test_sugerencia_mensual_hasta_la_fecha_meta(): void
    {
        // 90 días = 3 meses: 3,000.00 entre 3.
        $plan = WishlistService::suggestion(300000, '2026-01-01', '2026-04-01', 'mensual');

        self::assertNotNull($plan);
        self::assertSame('plan', $plan['status']);
        self::assertSame(90, $plan['days']);
        self::assertSame(3, $plan['months']);
        self::assertSame(100000, $plan['per_month']);
        self::assertSame(100000, $plan['per_period']);
    }

    public function test_sugerencia_cuenta_meses_de_calendario(): void
    {
        // Del 14 nov al 14 feb son 92 días pero exactamente 3 meses.
        $plan = WishlistService::suggestion(300000, '2026-11-14', '2027-02-14', 'mensual');
        self::assertSame(3, $plan['months'] ?? null);
        self::assertSame(100000, $plan['per_month'] ?? null);

        // Con días sobrantes se cuenta un mes más.
        $plan = WishlistService::suggestion(300000, '2026-11-14', '2027-02-20', 'mensual');
        self::assertSame(4, $plan['months'] ?? null);
        self::assertSame(75000, $plan['per_month'] ?? null);
    }

    public function test_sugerencia_por_periodo_semanal_redondea_hacia_arriba(): void
    {
        // 20 días = 3 semanas (2.86 redondeado hacia arriba).
        $plan = WishlistService::suggestion(100000, '2026-01-01', '2026-01-21', 'semanal');

        self::assertNotNull($plan);
        self::assertSame(3, $plan['periods']);
        self::assertSame(33334, $plan['per_period']);
        self::assertSame(1, $plan['months']);
        self::assertSame(100000, $plan['per_month']);
    }

    public function test_sugerencia_sin_fecha_meta(): void
    {
        self::assertNull(WishlistService::suggestion(100000, '2026-01-01', null, 'mensual'));
        self::assertNull(WishlistService::suggestion(100000, '2026-01-01', '', 'mensual'));
    }

    public function test_sugerencia_meta_cumplida_o_vencida(): void
    {
        self::assertSame('done', WishlistService::suggestion(0, '2026-01-01', '2026-04-01', 'mensual')['status'] ?? null);
        self::assertSame('done', WishlistService::suggestion(0, '2026-01-01', null, 'mensual')['status'] ?? null);

        $overdue = WishlistService::suggestion(50000, '2026-05-01', '2026-04-01', 'mensual');
        self::assertSame('overdue', $overdue['status'] ?? null);
        self::assertSame(50000, $overdue['remaining'] ?? null);

        self::assertSame('overdue', WishlistService::suggestion(50000, '2026-04-01', '2026-04-01', 'mensual')['status'] ?? null);
    }
}
