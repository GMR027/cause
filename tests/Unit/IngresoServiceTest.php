<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\IngresoService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class IngresoServiceTest extends TestCase
{
    private IngresoService $service;

    protected function setUp(): void
    {
        $this->service = new IngresoService();
    }

    public function test_reparto_por_metodo_respeta_porcentajes(): void
    {
        $split = $this->service->repartoPorMetodo($this->categories(), [
            'Necesidades' => 50,
            'Deseos' => 30,
            'Ahorro' => 20,
        ], 1000000);

        self::assertSame([
            'Necesidades' => 500000,
            'Deseos' => 300000,
            'Ahorro' => 200000,
        ], $split);
    }

    public function test_reparto_por_metodo_usa_porcentajes_guardados(): void
    {
        $split = $this->service->repartoPorMetodo($this->categories(), [
            'Necesidades' => 60,
            'Deseos' => 25,
            'Ahorro' => 15,
        ], 100000);

        self::assertSame([
            'Necesidades' => 60000,
            'Deseos' => 25000,
            'Ahorro' => 15000,
        ], $split);
    }

    public function test_reparto_por_metodo_asigna_residuo_al_mayor(): void
    {
        $split = $this->service->repartoPorMetodo($this->categories(), [
            'Necesidades' => 50,
            'Deseos' => 30,
            'Ahorro' => 20,
        ], 101);

        self::assertSame(['Necesidades' => 51, 'Deseos' => 30, 'Ahorro' => 20], $split);
        self::assertSame(101, array_sum($split));
    }

    public function test_reparto_por_metodo_rechaza_porcentajes_que_no_suman_100(): void
    {
        $this->expectException(\RuntimeException::class);

        $categories = $this->categories();
        $categories[2]['porcentaje'] = null;

        $this->service->repartoPorMetodo($categories, [
            'Necesidades' => 50,
            'Deseos' => 30,
        ], 1000);
    }

    public function test_extra_por_defecto_va_al_ahorro(): void
    {
        $split = $this->service->repartoExtra($this->categories(), null, 10000);

        self::assertSame(['Ahorro' => 10000], $split);
    }

    public function test_extra_respeta_regla_del_usuario(): void
    {
        $split = $this->service->repartoExtra($this->categories(), [
            'Ahorro' => 60,
            'Necesidades' => 40,
        ], 10000);

        self::assertSame(['Necesidades' => 4000, 'Ahorro' => 6000], $split);
    }

    public function test_extra_ignora_categorias_desconocidas_y_usa_fallback(): void
    {
        $split = $this->service->repartoExtra($this->categories(), [
            'Categoria inventada' => 100,
        ], 10000);

        self::assertSame(['Ahorro' => 10000], $split);
    }

    public function test_extra_usa_inversion_si_no_hay_ahorro(): void
    {
        $categories = [
            ['nombre' => 'Gastos', 'porcentaje' => 70, 'tipo' => 'controlada', 'orden' => 1],
            ['nombre' => 'Inversión', 'porcentaje' => 30, 'tipo' => 'inversion', 'orden' => 2],
        ];

        $split = $this->service->repartoExtra($categories, null, 5000);

        self::assertSame(['Inversión' => 5000], $split);
    }

    public function test_extra_sin_ahorro_ni_inversion_usa_la_primera(): void
    {
        $categories = [
            ['nombre' => 'Necesidades', 'porcentaje' => 100, 'tipo' => 'controlada', 'orden' => 1],
        ];

        self::assertSame('Necesidades', $this->service->categoriaExtraPorDefecto($categories));
    }

    public function test_monto_negativo_es_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->repartoPorMetodo($this->categories(), [
            'Necesidades' => 50,
            'Deseos' => 30,
            'Ahorro' => 20,
        ], -100);
    }

    /**
     * @return list<array{nombre: string, porcentaje: int, tipo: string, orden: int}>
     */
    private function categories(): array
    {
        return [
            ['nombre' => 'Necesidades', 'porcentaje' => 50, 'tipo' => 'controlada', 'orden' => 1],
            ['nombre' => 'Deseos', 'porcentaje' => 30, 'tipo' => 'controlada', 'orden' => 2],
            ['nombre' => 'Ahorro', 'porcentaje' => 20, 'tipo' => 'ahorro', 'orden' => 3],
        ];
    }
}
