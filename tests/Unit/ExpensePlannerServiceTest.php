<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\ExpensePlannerService;
use Cauce\Services\RepartoService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class ExpensePlannerServiceTest extends TestCase
{
    private ExpensePlannerService $service;

    /** @var list<array{nombre: string, porcentaje: int, tipo: string, orden: int}> */
    private array $categories;

    /** @var array<string, float> */
    private array $percentages;

    protected function setUp(): void
    {
        $this->service = new ExpensePlannerService();
        $this->categories = [
            ['nombre' => 'Gastos', 'porcentaje' => 50, 'tipo' => 'controlada', 'orden' => 1],
            ['nombre' => 'Ahorro', 'porcentaje' => 30, 'tipo' => 'ahorro', 'orden' => 2],
            ['nombre' => 'Inversión', 'porcentaje' => 20, 'tipo' => 'inversion', 'orden' => 3],
        ];
        $this->percentages = ['Gastos' => 50.0, 'Ahorro' => 30.0, 'Inversión' => 20.0];
    }

    public function test_sections_use_the_same_split_as_a_real_income(): void
    {
        $reparto = (new RepartoService())->repartirIngreso(100000, $this->percentages);

        $sections = $this->service->buildSections($this->categories, $this->percentages, $reparto, []);

        self::assertSame(50000, $sections[0]['asignado_centavos']);
        self::assertSame(30000, $sections[1]['asignado_centavos']);
        self::assertSame(20000, $sections[2]['asignado_centavos']);
        self::assertSame([], $this->service->buildSections($this->categories, $this->percentages, $reparto, [])[0]['commitments']);
    }

    public function test_commitments_and_items_are_grouped_by_section(): void
    {
        $reparto = (new RepartoService())->repartirIngreso(100000, $this->percentages);
        $commitments = [
            ['nombre' => 'Renta', 'monto_centavos' => 25000, 'seccion' => 'Gastos'],
            ['nombre' => 'Súper', 'monto_centavos' => 5000, 'seccion' => 'Gastos'],
            ['nombre' => 'Meta', 'monto_centavos' => 10000, 'seccion' => 'Ahorro'],
        ];
        $items = [
            ['seccion' => 'Gastos', 'concepto' => 'Comida', 'monto_centavos' => 5000],
        ];

        $sections = $this->service->buildSections($this->categories, $this->percentages, $reparto, $commitments, $items);

        self::assertSame(30000, $sections[0]['comprometido_centavos']);
        self::assertSame(20000, $sections[0]['disponible_centavos']);
        self::assertSame(5000, $sections[0]['items_centavos']);
        self::assertSame(15000, $sections[0]['items_disponible_centavos']);
        self::assertFalse($sections[0]['excedido']);
        self::assertSame(10000, $sections[1]['comprometido_centavos']);
        self::assertSame(20000, $sections[1]['disponible_centavos']);
    }

    public function test_section_can_be_over_committed(): void
    {
        $reparto = (new RepartoService())->repartirIngreso(100000, $this->percentages);
        $sections = $this->service->buildSections($this->categories, $this->percentages, $reparto, [
            ['nombre' => 'Renta', 'monto_centavos' => 60000, 'seccion' => 'Gastos'],
        ]);

        self::assertSame(-10000, $sections[0]['disponible_centavos']);
        self::assertTrue($sections[0]['excedido']);
    }

    public function test_items_over_the_free_amount_are_flagged(): void
    {
        $reparto = (new RepartoService())->repartirIngreso(100000, $this->percentages);
        $sections = $this->service->buildSections($this->categories, $this->percentages, $reparto, [], [
            ['seccion' => 'Gastos', 'concepto' => 'Comida', 'monto_centavos' => 40000],
        ]);

        self::assertSame(10000, $sections[0]['items_disponible_centavos']);
        self::assertFalse($sections[0]['items_excedido']);

        $sections = $this->service->buildSections($this->categories, $this->percentages, $reparto, [], [
            ['seccion' => 'Gastos', 'concepto' => 'Comida', 'monto_centavos' => 60000],
        ]);

        self::assertSame(-10000, $sections[0]['items_disponible_centavos']);
        self::assertTrue($sections[0]['items_excedido']);
    }

    public function test_unknown_section_falls_into_sin_seccion(): void
    {
        $reparto = (new RepartoService())->repartirIngreso(100000, $this->percentages);
        $sections = $this->service->buildSections($this->categories, $this->percentages, $reparto, [
            ['nombre' => 'Otro', 'monto_centavos' => 1000, 'seccion' => 'Inexistente'],
        ]);

        $orphan = null;

        foreach ($sections as $section) {
            if ($section['nombre'] === ExpensePlannerService::SECTION_SIN_SECCION) {
                $orphan = $section;
            }
        }

        self::assertNotNull($orphan);
        self::assertSame(1000, $orphan['comprometido_centavos']);
    }

    public function test_summary_separates_obligations_reserved_and_spendable(): void
    {
        $reparto = (new RepartoService())->repartirIngreso(100000, $this->percentages);
        $commitments = [
            ['nombre' => 'Renta', 'monto_centavos' => 30000, 'seccion' => 'Gastos'],
            ['nombre' => 'Meta', 'monto_centavos' => 10000, 'seccion' => 'Ahorro'],
        ];
        $sections = $this->service->buildSections($this->categories, $this->percentages, $reparto, $commitments);
        $summary = $this->service->summarize($sections, $commitments, 100000);

        self::assertSame(40000, $summary['obligaciones_centavos']);
        self::assertSame(50000, $summary['apartados_centavos']);
        self::assertSame(20000, $summary['disponible_gastar_centavos']);
        self::assertSame(60000, $summary['despues_obligaciones_centavos']);
        self::assertSame(10000, $summary['sobrante_centavos']);
        self::assertSame(0, $summary['deficit_centavos']);
    }

    public function test_summary_reports_deficit_when_commitments_exceed_income(): void
    {
        $sections = $this->service->buildSections($this->categories, $this->percentages, [
            'Gastos' => 50000,
            'Ahorro' => 30000,
            'Inversión' => 20000,
        ], [
            ['nombre' => 'Renta', 'monto_centavos' => 120000, 'seccion' => 'Gastos'],
        ]);
        $summary = $this->service->summarize($sections, [
            ['nombre' => 'Renta', 'monto_centavos' => 120000, 'seccion' => 'Gastos'],
        ], 100000);

        self::assertSame(20000, $summary['deficit_centavos']);
        self::assertSame(-20000, $summary['despues_obligaciones_centavos']);
    }

    public function test_normalize_items_parses_amounts_and_skips_invalid_rows(): void
    {
        $warnings = [];
        $items = $this->service->normalizeItems([
            ['seccion' => 'Gastos', 'concepto' => 'Renta', 'monto' => '1,500.50'],
            ['seccion' => 'Otra', 'concepto' => 'Mudanza', 'monto' => '10'],
            ['seccion' => 'Gastos', 'concepto' => '', 'monto' => '20'],
            ['seccion' => 'Gastos', 'concepto' => 'Cero', 'monto' => '0'],
            ['seccion' => 'Gastos', 'concepto' => '', 'monto' => ''],
        ], ['Gastos', 'Ahorro', 'Inversión'], $warnings);

        self::assertCount(2, $items);
        self::assertSame(150050, $items[0]['monto_centavos']);
        self::assertSame(ExpensePlannerService::SECTION_SIN_SECCION, $items[1]['seccion']);
        self::assertCount(3, $warnings);
    }

    public function test_validate_accepts_a_future_date_and_parses_the_amount(): void
    {
        $result = $this->service->validate($this->user(), [
            'fecha_ingreso' => '2026-11-20',
            'monto' => '1,500.00',
            'moneda' => 'MXN',
        ], $this->now());

        self::assertSame([], $result['errors']);
        self::assertSame('2026-11-20', $result['fecha_ingreso']);
        self::assertSame(150000, $result['monto_centavos']);
    }

    public function test_validate_rejects_past_dates_and_invalid_amounts(): void
    {
        $result = $this->service->validate($this->user(), [
            'fecha_ingreso' => '2026-11-14',
            'monto' => '0',
            'moneda' => 'MXN',
        ], $this->now());

        self::assertContains('planner.errors.date_past', $result['errors']);
        self::assertContains('planner.errors.amount_invalid', $result['errors']);
    }

    public function test_validate_rejects_dates_too_far_ahead(): void
    {
        $result = $this->service->validate($this->user(), [
            'fecha_ingreso' => '2027-12-01',
            'monto' => '100',
            'moneda' => 'MXN',
        ], $this->now());

        self::assertContains('planner.errors.date_far', $result['errors']);
    }

    public function test_validate_rejects_unknown_currency(): void
    {
        $result = $this->service->validate($this->user(), [
            'fecha_ingreso' => '2026-11-20',
            'monto' => '100',
            'moneda' => 'XYZ',
        ], $this->now());

        self::assertContains('planner.errors.currency_invalid', $result['errors']);
    }

    /**
     * @return array<string, mixed>
     */
    private function user(): array
    {
        return [
            'id' => 1,
            'moneda_activa' => 'MXN',
            'zona_horaria' => 'America/Mexico_City',
        ];
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-15 18:00:00', new DateTimeZone('UTC'));
    }
}
