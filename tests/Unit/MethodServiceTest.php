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
    public function test_clean_percentages_rellena_ceros_y_valida_suma(): void
    {
        $result = $this->service->cleanPercentages($this->categories503020(), [
            'Necesidades' => '55',
            'Deseos' => '25,5',
            'Ahorro' => '19.5',
        ]);

        self::assertSame([], $result['errors']);
        self::assertSame(
            ['Necesidades' => 55.0, 'Deseos' => 25.5, 'Ahorro' => 19.5, 'Donación' => 0.0],
            $result['percentages']
        );

        $bad = $this->service->cleanPercentages($this->categories503020(), ['Necesidades' => 50, 'Ahorro' => 20]);
        self::assertContains('settings.metodo.errors.sum', $bad['errors']);

        $noSpending = $this->service->cleanPercentages($this->categories503020(), ['Ahorro' => 100]);
        self::assertContains('settings.metodo.errors.no_spending', $noSpending['errors']);
    }

    public function test_clean_custom_valida_filas(): void
    {
        $ok = $this->service->cleanCustom('  Mi   reparto ', [
            ['nombre' => 'Casa', 'tipo' => 'controlada', 'porcentaje' => '60'],
            ['nombre' => '', 'tipo' => 'ahorro', 'porcentaje' => ''],
            ['nombre' => 'Colchón', 'tipo' => 'ahorro', 'porcentaje' => '25'],
            ['nombre' => 'Bolsa', 'tipo' => 'inversion', 'porcentaje' => '15', 'id' => '7'],
        ]);

        self::assertSame([], $ok['errors']);
        self::assertSame('Mi reparto', $ok['nombre']);
        self::assertCount(3, $ok['categorias']);
        self::assertSame(7, $ok['categorias'][2]['id']);

        $bad = $this->service->cleanCustom('X', [
            ['nombre' => 'Casa', 'tipo' => 'controlada', 'porcentaje' => '50'],
            ['nombre' => 'casa', 'tipo' => 'ahorro', 'porcentaje' => '50'],
            ['nombre' => 'Otro', 'tipo' => 'no_controlado', 'porcentaje' => '10'],
        ]);

        self::assertContains('settings.metodo.errors.name', $bad['errors']);
        self::assertContains('settings.metodo.errors.duplicate', $bad['errors']);
        self::assertContains('settings.metodo.errors.type', $bad['errors']);

        $noSpending = $this->service->cleanCustom('Solo ahorro', [
            ['nombre' => 'Ahorro', 'tipo' => 'ahorro', 'porcentaje' => '100'],
        ]);
        self::assertContains('settings.metodo.errors.no_spending', $noSpending['errors']);
    }

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
