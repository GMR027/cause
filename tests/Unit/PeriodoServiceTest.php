<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\PeriodoService;
use PHPUnit\Framework\TestCase;

final class PeriodoServiceTest extends TestCase
{
    private PeriodoService $service;

    protected function setUp(): void
    {
        $this->service = new PeriodoService();
    }

    public function test_mensual_dia_15(): void
    {
        $period = $this->service->calcularPeriodo($this->user(15), '2026-10-20 12:00:00');

        self::assertSame('2026-10-15 06:00:00', $this->format($period['inicio']));
        self::assertSame('2026-11-15 05:59:59', $this->format($period['fin']));
    }

    public function test_mensual_antes_del_dia_de_corte(): void
    {
        $period = $this->service->calcularPeriodo($this->user(15), '2026-10-10 12:00:00');

        self::assertSame('2026-09-15 06:00:00', $this->format($period['inicio']));
        self::assertSame('2026-10-15 05:59:59', $this->format($period['fin']));
    }

    public function test_febrero_no_bisiesto(): void
    {
        $period = $this->service->calcularPeriodo($this->user(15), '2026-02-20 12:00:00');

        self::assertSame('2026-02-15 06:00:00', $this->format($period['inicio']));
        self::assertSame('2026-03-15 05:59:59', $this->format($period['fin']));
    }

    public function test_febrero_bisiesto(): void
    {
        $period = $this->service->calcularPeriodo($this->user(15), '2028-02-29 12:00:00');

        self::assertSame('2028-02-15 06:00:00', $this->format($period['inicio']));
        self::assertSame('2028-03-15 05:59:59', $this->format($period['fin']));
    }

    public function test_dia_31_se_ajusta_a_febrero(): void
    {
        $period = $this->service->calcularPeriodo($this->user(31), '2026-02-20 12:00:00');

        self::assertSame('2026-01-31 06:00:00', $this->format($period['inicio']));
        self::assertSame('2026-02-28 05:59:59', $this->format($period['fin']));
    }

    public function test_dia_31_en_bisiesto(): void
    {
        $period = $this->service->calcularPeriodo($this->user(31), '2028-03-01 12:00:00');

        self::assertSame('2028-02-29 06:00:00', $this->format($period['inicio']));
        self::assertSame('2028-03-31 05:59:59', $this->format($period['fin']));
    }

    public function test_diaria(): void
    {
        $period = $this->service->calcularPeriodo($this->user(1, 'diaria'), '2026-10-20 15:00:00');

        self::assertSame('2026-10-20 06:00:00', $this->format($period['inicio']));
        self::assertSame('2026-10-21 05:59:59', $this->format($period['fin']));
    }

    public function test_semanal_lunes_a_domingo(): void
    {
        $period = $this->service->calcularPeriodo($this->user(1, 'semanal'), '2026-10-20 15:00:00');

        self::assertSame('2026-10-19 06:00:00', $this->format($period['inicio']));
        self::assertSame('2026-10-26 05:59:59', $this->format($period['fin']));
    }

    public function test_anual_ano_calendario(): void
    {
        $period = $this->service->calcularPeriodo($this->user(1, 'anual'), '2026-10-20 15:00:00');

        self::assertSame('2026-01-01 06:00:00', $this->format($period['inicio']));
        self::assertSame('2027-01-01 05:59:59', $this->format($period['fin']));
    }

    public function test_cambio_de_recurrencia_termina_el_periodo_actual(): void
    {
        $user = $this->user(15);
        $plan = $this->service->planificarCambioRecurrencia($user, 'semanal', '2026-10-20 12:00:00');

        self::assertSame('semanal', $plan['recurrencia']);
        self::assertSame('mensual', $plan['recurrencia_anterior']);
        self::assertSame('2026-11-15 06:00:00', $plan['recurrencia_cambio_desde']);

        $changed = array_merge($user, $plan);

        $before = $this->service->calcularPeriodo($changed, '2026-11-10 12:00:00');
        self::assertSame('2026-10-15 06:00:00', $this->format($before['inicio']));
        self::assertSame('2026-11-15 05:59:59', $this->format($before['fin']));

        $after = $this->service->calcularPeriodo($changed, '2026-11-20 12:00:00');
        self::assertSame('2026-11-16 06:00:00', $this->format($after['inicio']));
        self::assertSame('2026-11-23 05:59:59', $this->format($after['fin']));
    }

    public function test_primer_periodo_nuevo_se_recorta_al_cambio(): void
    {
        $user = array_merge(
            $this->user(15),
            $this->service->planificarCambioRecurrencia($this->user(15), 'semanal', '2026-10-20 12:00:00')
        );

        $period = $this->service->calcularPeriodo($user, '2026-11-15 12:00:00');

        self::assertSame('2026-11-15 06:00:00', $this->format($period['inicio']));
        self::assertSame('2026-11-16 05:59:59', $this->format($period['fin']));
    }

    public function test_recurrencia_invalida_cae_a_mensual(): void
    {
        self::assertSame('mensual', $this->service->normalizeRecurrence('trimestral'));
    }

    /**
     * @return array<string, mixed>
     */
    private function user(int $startDay, string $recurrence = 'mensual'): array
    {
        return [
            'periodo_inicio_dia' => $startDay,
            'recurrencia' => $recurrence,
            'recurrencia_anterior' => null,
            'recurrencia_cambio_desde' => null,
            'zona_horaria' => 'America/Mexico_City',
        ];
    }

    private function format(\DateTimeInterface $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
