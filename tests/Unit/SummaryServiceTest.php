<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\SummaryService;
use PHPUnit\Framework\TestCase;

final class SummaryServiceTest extends TestCase
{
    public function test_resumen_vacio_no_tiene_actividad(): void
    {
        self::assertFalse((new SummaryService())->hasActivity([
            'income_centavos' => 0,
            'expense_centavos' => 0,
            'savings_centavos' => 0,
            'investment_centavos' => 0,
            'donation_centavos' => 0,
            'moves' => [],
            'withdrawals' => [],
            'contributions' => [],
        ]));
    }

    public function test_resumen_con_ingreso_tiene_actividad(): void
    {
        self::assertTrue((new SummaryService())->hasActivity([
            'income_centavos' => 100000,
        ]));
    }

    public function test_resumen_con_movimientos_tiene_actividad(): void
    {
        self::assertTrue((new SummaryService())->hasActivity([
            'moves' => [['origen' => 'Necesidades', 'destino' => 'Deseos']],
        ]));
    }

    public function test_resumen_con_aportaciones_tiene_actividad(): void
    {
        self::assertTrue((new SummaryService())->hasActivity([
            'contributions' => [['tipo' => 'ahorro', 'monto_centavos' => 5000]],
        ]));
    }
}
