<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\MethodService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MethodServiceTest extends TestCase
{
    private MethodService $service;

    protected function setUp(): void
    {
        $this->service = new MethodService();
    }

    public function test_build_percentages_usa_catalogo_y_guardados(): void
    {
        $categories = $this->categories503020();
        $result = $this->service->buildPercentages($categories, [
            'Donación' => 5,
            'Necesidades' => 45,
        ]);

        self::assertSame(45.0, $result['Necesidades']);
        self::assertSame(30.0, $result['Deseos']);
        self::assertSame(20.0, $result['Ahorro']);
        self::assertSame(5.0, $result['Donación']);
    }

    public function test_activar_donacion_reduce_la_mayor_controlada(): void
    {
        $adjusted = $this->service->donationAdjusted(
            $this->categories503020(),
            ['Necesidades' => 50.0, 'Deseos' => 30.0, 'Ahorro' => 20.0],
            5.0
        );

        self::assertSame(45.0, $adjusted['Necesidades']);
        self::assertSame(5.0, $adjusted['Donación']);
        self::assertEqualsWithDelta(100.0, array_sum($adjusted), 0.001);
    }

    public function test_desactivar_donacion_devuelve_el_porcentaje(): void
    {
        $adjusted = $this->service->donationAdjusted(
            $this->categories503020(),
            ['Necesidades' => 45.0, 'Deseos' => 30.0, 'Ahorro' => 20.0, 'Donación' => 5.0],
            0.0
        );

        self::assertSame(50.0, $adjusted['Necesidades']);
        self::assertSame(0.0, $adjusted['Donación']);
        self::assertEqualsWithDelta(100.0, array_sum($adjusted), 0.001);
    }

    public function test_donacion_fuera_de_rango(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->donationAdjusted(
            $this->categories503020(),
            ['Necesidades' => 50.0, 'Deseos' => 30.0, 'Ahorro' => 20.0],
            101.0
        );
    }

    public function test_ajustar_inversion_a_cero_mueve_a_ahorro(): void
    {
        $adjusted = $this->service->investmentAdjusted(
            $this->categories702010(),
            ['Gastos' => 70.0, 'Ahorro' => 20.0, 'Deudas/Inversión' => 10.0],
            0.0
        );

        self::assertSame(0.0, $adjusted['Deudas/Inversión']);
        self::assertSame(30.0, $adjusted['Ahorro']);
        self::assertEqualsWithDelta(100.0, array_sum($adjusted), 0.001);
    }

    public function test_ajustar_inversion_a_quince(): void
    {
        $adjusted = $this->service->investmentAdjusted(
            $this->categories702010(),
            ['Gastos' => 70.0, 'Ahorro' => 20.0, 'Deudas/Inversión' => 10.0],
            15.0
        );

        self::assertSame(15.0, $adjusted['Deudas/Inversión']);
        self::assertSame(15.0, $adjusted['Ahorro']);
        self::assertEqualsWithDelta(100.0, array_sum($adjusted), 0.001);
    }

    public function test_metodo_sin_inversion_no_se_puede_ajustar(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->investmentAdjusted(
            $this->categories503020(),
            ['Necesidades' => 50.0, 'Deseos' => 30.0, 'Ahorro' => 20.0],
            10.0
        );
    }

    /**
     * @return list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}>
     */
    private function categories503020(): array
    {
        return [
            ['nombre' => 'Necesidades', 'porcentaje' => 50, 'tipo' => 'controlada', 'orden' => 1],
            ['nombre' => 'Deseos', 'porcentaje' => 30, 'tipo' => 'controlada', 'orden' => 2],
            ['nombre' => 'Ahorro', 'porcentaje' => 20, 'tipo' => 'ahorro', 'orden' => 3],
            ['nombre' => 'Donación', 'porcentaje' => null, 'tipo' => 'donacion', 'orden' => 4],
        ];
    }

    /**
     * @return list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}>
     */
    private function categories702010(): array
    {
        return [
            ['nombre' => 'Gastos', 'porcentaje' => 70, 'tipo' => 'controlada', 'orden' => 1],
            ['nombre' => 'Ahorro', 'porcentaje' => 20, 'tipo' => 'ahorro', 'orden' => 2],
            ['nombre' => 'Deudas/Inversión', 'porcentaje' => 10, 'tipo' => 'inversion', 'orden' => 3],
            ['nombre' => 'Donación', 'porcentaje' => null, 'tipo' => 'donacion', 'orden' => 4],
        ];
    }
}
