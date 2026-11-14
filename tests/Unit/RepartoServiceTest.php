<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\RepartoService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RepartoServiceTest extends TestCase
{
    private RepartoService $service;

    protected function setUp(): void
    {
        $this->service = new RepartoService();
    }

    public function test_reparto_exacto_50_30_20(): void
    {
        $result = $this->service->repartirIngreso(10000, [
            'Necesidades' => 50,
            'Deseos' => 30,
            'Ahorro' => 20,
        ]);

        self::assertSame([
            'Necesidades' => 5000,
            'Deseos' => 3000,
            'Ahorro' => 2000,
        ], $result);
    }

    public function test_reparto_con_porcentaje_33_33(): void
    {
        $result = $this->service->repartirIngreso(100, [
            'A' => 33.33,
            'B' => 33.33,
            'C' => 33.34,
        ]);

        self::assertSame(['A' => 33, 'B' => 33, 'C' => 34], $result);
        self::assertSame(100, array_sum($result));
    }

    public function test_reparto_con_un_tercio_y_empate(): void
    {
        $result = $this->service->repartirIngreso(100, [
            'A' => 33.3333,
            'B' => 33.3333,
            'C' => 33.3333,
        ]);

        self::assertSame(['A' => 34, 'B' => 33, 'C' => 33], $result);
        self::assertSame(100, array_sum($result));
    }

    public function test_reparto_con_siete_categorias(): void
    {
        $porcentajes = array_fill_keys(['A', 'B', 'C', 'D', 'E', 'F', 'G'], 14.2857);

        $result = $this->service->repartirIngreso(100, $porcentajes);

        self::assertSame(['A' => 16, 'B' => 14, 'C' => 14, 'D' => 14, 'E' => 14, 'F' => 14, 'G' => 14], $result);
        self::assertSame(100, array_sum($result));
    }

    public function test_reparto_con_centavos_y_residuo_mayor(): void
    {
        $result = $this->service->repartirIngreso(99999, [
            'Necesidades' => 50,
            'Deseos' => 30,
            'Ahorro' => 20,
        ]);

        self::assertSame(['Necesidades' => 50001, 'Deseos' => 29999, 'Ahorro' => 19999], $result);
        self::assertSame(99999, array_sum($result));
    }

    public function test_reparto_de_un_centavo(): void
    {
        $result = $this->service->repartirIngreso(1, [
            'A' => 50,
            'B' => 30,
            'C' => 20,
        ]);

        self::assertSame(['A' => 1, 'B' => 0, 'C' => 0], $result);
    }

    public function test_residuo_se_asigna_a_la_categoria_mayor(): void
    {
        $result = $this->service->repartirIngreso(101, [
            'A' => 50,
            'B' => 30,
            'C' => 20,
        ]);

        self::assertSame(['A' => 51, 'B' => 30, 'C' => 20], $result);
        self::assertSame(101, array_sum($result));
    }

    public function test_reparto_grande_conserva_el_total(): void
    {
        $result = $this->service->repartirIngreso(123456789, [
            'A' => 33.33,
            'B' => 33.33,
            'C' => 33.34,
        ]);

        self::assertSame(123456789, array_sum($result));
    }

    public function test_porcentajes_invalidos_lanzan_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->repartirIngreso(10000, [
            'A' => 50,
            'B' => 40,
        ]);
    }

    public function test_monto_negativo_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->repartirIngreso(-1, ['A' => 100]);
    }

    public function test_lista_vacia_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->repartirIngreso(100, []);
    }
}
