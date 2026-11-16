<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\FrequentExpensesService;
use Cauce\Services\PortfolioService;
use PHPUnit\Framework\TestCase;

final class PortfolioServiceTest extends TestCase
{
    private const YEAR = '2026-01-01 00:00:00';

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function position(int $id, int $portfolio, string $mode, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'portafolio_id' => $portfolio,
            'tipo' => 'sofipo',
            'nombre' => 'Instrumento ' . $id,
            'modo_valor' => $mode,
            'costo_principal_centavos' => 0,
            'valor_principal_centavos' => 0,
            'valor_manual_principal_centavos' => null,
            'fecha_valor_manual' => null,
            'precio_actual' => null,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function move(string $kind, int $portfolio, ?int $position, int $amount, string $date = '2026-03-01 12:00:00', string $key = 'otro'): array
    {
        return ['kind' => $kind, 'portafolio_id' => $portfolio, 'posicion_id' => $position, 'instrumento' => $key, 'monto' => $amount, 'fecha' => $date];
    }

    /**
     * @return array<string, mixed>
     */
    private function yield(string $type, int $portfolio, ?int $position, int $amount, string $destination = 'reinvertido', string $date = '2026-04-01 12:00:00'): array
    {
        return ['tipo' => $type, 'portafolio_id' => $portfolio, 'posicion_id' => $position, 'monto' => $amount, 'destino' => $destination, 'fecha' => $date];
    }

    private function portfolio(int $id, ?string $legacy = null): array
    {
        return ['id' => $id, 'nombre' => 'P' . $id, 'clase' => 'sofipo', 'institucion' => null, 'instrumento_legado' => $legacy];
    }

    public function test_calculado_suma_intereses_reinvertidos_y_resta_bajas_y_comisiones(): void
    {
        $result = PortfolioService::build(
            [$this->portfolio(1)],
            [$this->position(10, 1, 'calculado')],
            [$this->move('in', 1, 10, 100000)],
            [
                $this->yield('interes', 1, 10, 5000),
                $this->yield('baja', 1, 10, 2000),
                $this->yield('comision', 1, 10, 500),
            ],
            self::YEAR
        );

        $instrument = $result['portfolios'][0]['instruments'][0];
        self::assertSame(102500, $instrument['valor_principal_centavos']);
        self::assertSame(100000, $instrument['costo_principal_centavos']);
        self::assertSame(2500, $instrument['ganancia_centavos']);
        self::assertSame(2.5, $instrument['ganancia_pct']);
        self::assertSame(102500, $result['totals']['value']);
        self::assertSame(5000, $result['totals']['yields_year']);
    }

    public function test_interes_pagado_no_sube_el_valor_pero_si_la_ganancia(): void
    {
        $result = PortfolioService::build(
            [$this->portfolio(1)],
            [$this->position(10, 1, 'calculado')],
            [$this->move('in', 1, 10, 100000)],
            [$this->yield('interes', 1, 10, 3000, 'pagado')],
            self::YEAR
        );

        $instrument = $result['portfolios'][0]['instruments'][0];
        self::assertSame(100000, $instrument['valor_principal_centavos']);
        self::assertSame(3000, $instrument['ganancia_centavos']);
        self::assertSame(3.0, $result['totals']['gain_pct']);
    }

    public function test_efectivo_del_portafolio_y_costo_inicial_legado(): void
    {
        // Como el antiguo «aportado sin asignar»: 10,000 aportados al tipo y una
        // posición con costo 6,000 dejan 4,000 de efectivo.
        $result = PortfolioService::build(
            [$this->portfolio(1, 'cetes')],
            [$this->position(10, 1, 'manual', [
                'tipo' => 'cetes',
                'costo_principal_centavos' => 600000,
                'valor_manual_principal_centavos' => 650000,
                'fecha_valor_manual' => '2026-02-01 12:00:00',
            ])],
            [$this->move('in', 0, null, 1000000, '2026-01-15 12:00:00', 'cetes')],
            [],
            self::YEAR
        );

        $portfolio = $result['portfolios'][0];
        self::assertSame(400000, $portfolio['cash']);
        self::assertSame(1050000, $portfolio['value']);
        self::assertSame(1000000, $portfolio['invested']);
        self::assertSame(50000, $portfolio['gain']);
    }

    public function test_manual_suma_lo_ocurrido_despues_del_ultimo_valor(): void
    {
        $result = PortfolioService::build(
            [$this->portfolio(1)],
            [$this->position(10, 1, 'manual', [
                'valor_manual_principal_centavos' => 50000,
                'fecha_valor_manual' => '2026-03-10 12:00:00',
            ])],
            [
                $this->move('in', 1, 10, 40000, '2026-03-01 12:00:00'),
                $this->move('in', 1, 10, 10000, '2026-03-20 12:00:00'),
            ],
            [$this->yield('baja', 1, 10, 1000, 'reinvertido', '2026-03-25 12:00:00')],
            self::YEAR
        );

        self::assertSame(59000, $result['portfolios'][0]['instruments'][0]['valor_principal_centavos']);
    }

    public function test_automatico_sin_cotizacion_usa_el_costo(): void
    {
        $result = PortfolioService::build(
            [$this->portfolio(1)],
            [$this->position(10, 1, 'automatico', ['valor_principal_centavos' => 999])],
            [$this->move('in', 1, 10, 20000)],
            [],
            self::YEAR
        );

        self::assertSame(20000, $result['portfolios'][0]['instruments'][0]['valor_principal_centavos']);
    }

    public function test_movimientos_sin_portafolio_se_ligan_por_clave_legada(): void
    {
        $result = PortfolioService::build(
            [$this->portfolio(1, 'cripto'), $this->portfolio(2, 'u7')],
            [],
            [
                $this->move('in', 0, null, 3000, '2026-02-01 00:00:00', 'cripto'),
                $this->move('in', 0, null, 5000, '2026-02-01 00:00:00', 'u7'),
                $this->move('out', 0, null, 1000, '2026-02-02 00:00:00', 'u7'),
            ],
            [],
            self::YEAR
        );

        self::assertCount(2, $result['portfolios']);
        self::assertSame(3000, $result['portfolios'][0]['cash']);
        self::assertSame(4000, $result['portfolios'][1]['cash']);
        self::assertSame(7000, $result['totals']['value']);
    }

    public function test_rendimientos_de_otro_anio_no_cuentan_en_el_anio(): void
    {
        $result = PortfolioService::build(
            [$this->portfolio(1)],
            [],
            [$this->move('in', 1, null, 10000)],
            [$this->yield('interes', 1, null, 700, 'reinvertido', '2025-12-30 12:00:00')],
            self::YEAR
        );

        self::assertSame(0, $result['totals']['yields_year']);
        self::assertSame(10700, $result['totals']['value']);
        self::assertSame(700, $result['totals']['gain']);
    }

    public function test_mapeo_de_clases(): void
    {
        self::assertSame('cetes_bonos', PortfolioService::classForLegacy('cetes'));
        self::assertSame('acciones', PortfolioService::classForLegacy('acciones'));
        self::assertSame('otro', PortfolioService::classForLegacy('u12'));
        self::assertSame('cetes', PortfolioService::legacyForClass('cetes_bonos'));
        self::assertSame('otro', PortfolioService::legacyForClass('sofipo'));
        self::assertSame('acciones', PortfolioService::legacyForPositionType('etf'));
    }

    public function test_top_frecuentes_ordena_y_limita(): void
    {
        $rows = [
            ['subcategoria_nombre' => 'Café', 'nota' => '', 'parte_metodo' => 'Gustos', 'veces' => 3, 'total' => 9000, 'ultima' => '2026-11-10'],
            ['subcategoria_nombre' => null, 'nota' => 'Uber', 'parte_metodo' => 'Necesidades', 'veces' => 5, 'total' => 50000, 'ultima' => '2026-11-11'],
            ['subcategoria_nombre' => 'Super', 'nota' => '', 'parte_metodo' => 'Necesidades', 'veces' => 3, 'total' => 90000, 'ultima' => '2026-11-12'],
            ['subcategoria_nombre' => '', 'nota' => '  ', 'parte_metodo' => 'Gustos', 'veces' => 9, 'total' => 100, 'ultima' => '2026-11-12'],
        ];

        $top = FrequentExpensesService::rank($rows, 2);

        self::assertCount(2, $top);
        self::assertSame('Uber', $top[0]['label']);
        self::assertSame(10000, $top[0]['promedio']);
        self::assertSame('Super', $top[1]['label']);
    }
}
