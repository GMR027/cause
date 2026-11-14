<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\GastoService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GastoServiceTest extends TestCase
{
    private GastoService $service;

    protected function setUp(): void
    {
        $this->service = new GastoService();
    }

    public function test_gasto_dentro_del_disponible(): void
    {
        $result = $this->service->calcularRepartoGasto(10000, 30000);

        self::assertSame(10000, $result['cubierto_centavos']);
        self::assertSame(0, $result['exceso_centavos']);
        self::assertFalse($result['excedido']);
    }

    public function test_gasto_que_excede_el_disponible(): void
    {
        $result = $this->service->calcularRepartoGasto(50000, 30000);

        self::assertSame(30000, $result['cubierto_centavos']);
        self::assertSame(20000, $result['exceso_centavos']);
        self::assertTrue($result['excedido']);
    }

    public function test_gasto_sin_disponible(): void
    {
        $result = $this->service->calcularRepartoGasto(1500, 0);

        self::assertSame(0, $result['cubierto_centavos']);
        self::assertSame(1500, $result['exceso_centavos']);
        self::assertTrue($result['excedido']);
    }

    public function test_disponible_negativo_se_trata_como_cero(): void
    {
        $result = $this->service->calcularRepartoGasto(1500, -500);

        self::assertSame(0, $result['cubierto_centavos']);
        self::assertSame(1500, $result['exceso_centavos']);
    }

    public function test_monto_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->calcularRepartoGasto(0, 100);
    }

    public function test_solo_el_periodo_actual_es_editable(): void
    {
        $user = [
            'periodo_inicio_dia' => 15,
            'recurrencia' => 'mensual',
            'zona_horaria' => 'America/Mexico_City',
        ];
        $period = $this->service->periodoActual($user);

        self::assertTrue($this->service->puedeEditar([
            'estado' => 'asignado',
            'periodo_inicio' => $period['inicio'],
            'periodo_fin' => $period['fin'],
        ], $user));

        self::assertFalse($this->service->puedeEditar([
            'estado' => 'asignado',
            'periodo_inicio' => '2020-01-01 00:00:00',
            'periodo_fin' => '2020-01-31 23:59:59',
        ], $user));
    }
}
