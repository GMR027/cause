<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\CalendarExportService;
use Cauce\Services\LessonService;
use Cauce\Services\ScoreService;
use PHPUnit\Framework\TestCase;

final class Phase10Test extends TestCase
{
    public function test_score_de_ahorro(): void
    {
        self::assertSame(0.0, ScoreService::savingsScore(1000, 0));
        self::assertSame(0.0, ScoreService::savingsScore(-1000, 10000));
        self::assertSame(50.0, ScoreService::savingsScore(1000, 10000));
        self::assertSame(100.0, ScoreService::savingsScore(2000, 10000));
        self::assertSame(100.0, ScoreService::savingsScore(5000, 10000));
    }

    public function test_score_de_deudas(): void
    {
        self::assertSame(100.0, ScoreService::debtScore(0, 10000));
        self::assertSame(50.0, ScoreService::debtScore(2500, 10000));
        self::assertSame(0.0, ScoreService::debtScore(5000, 10000));
        self::assertSame(0.0, ScoreService::debtScore(9000, 10000));
        self::assertSame(0.0, ScoreService::debtScore(1000, 0));
    }

    public function test_score_de_fondo_de_emergencia(): void
    {
        self::assertSame(0.0, ScoreService::emergencyScore(1000, 0));
        self::assertSame(0.0, ScoreService::emergencyScore(0, 10000));
        self::assertSame(50.0, ScoreService::emergencyScore(15000, 10000));
        self::assertSame(100.0, ScoreService::emergencyScore(30000, 10000));
        self::assertSame(100.0, ScoreService::emergencyScore(60000, 10000));
    }

    public function test_score_de_patrimonio_y_tendencia(): void
    {
        self::assertSame(0.0, ScoreService::netWorthScore(-100, null));
        self::assertSame(60.0, ScoreService::netWorthScore(100, null));
        self::assertSame(100.0, ScoreService::netWorthScore(200, 100));
        self::assertSame(60.0, ScoreService::netWorthScore(100, 100));

        self::assertNull(ScoreService::trend(null, 10));
        self::assertNull(ScoreService::trend(10, null));
        self::assertSame('up', ScoreService::trend(11, 10));
        self::assertSame('down', ScoreService::trend(9, 10));
        self::assertSame('same', ScoreService::trend(10, 10));
    }

    public function test_calendario_ics_incluye_recurrencia_y_recordatorio(): void
    {
        $service = new CalendarExportService();
        $ics = $service->build([
            [
                'id' => 7,
                'nombre' => 'Netflix; plan, familiar',
                'monto_centavos' => 20000,
                'moneda' => 'MXN',
                'recurrencia' => 'mensual',
                'proxima_ejecucion' => '2026-12-01 15:00:00',
                'deuda_id' => null,
            ],
            [
                'id' => 8,
                'nombre' => 'Pago tarjeta',
                'monto_centavos' => 50000,
                'moneda' => 'MXN',
                'recurrencia' => 'semanal',
                'proxima_ejecucion' => '2026-12-02 15:00:00',
                'deuda_id' => 3,
            ],
        ], 'MXN');

        self::assertStringContainsString('BEGIN:VCALENDAR', $ics);
        self::assertStringContainsString('RRULE:FREQ=MONTHLY', $ics);
        self::assertStringContainsString('RRULE:FREQ=WEEKLY', $ics);
        self::assertStringContainsString('SUMMARY:Cauce · Netflix\\; plan\\, familiar', $ics);
        self::assertStringContainsString('Recordatorio de deuda', str_replace("\r\n ", '', $ics));
        self::assertStringContainsString("\r\n", $ics);
    }

    public function test_calendario_ics_pliega_lineas_largas(): void
    {
        $service = new CalendarExportService();
        $ics = $service->build([
            [
                'id' => 9,
                'nombre' => str_repeat('Suscripción larga ', 8),
                'monto_centavos' => 1000,
                'moneda' => 'MXN',
                'recurrencia' => 'anual',
                'proxima_ejecucion' => '2026-12-03 15:00:00',
                'deuda_id' => null,
            ],
        ]);

        self::assertStringContainsString("\r\n ", $ics);
        self::assertStringContainsString('RRULE:FREQ=YEARLY', $ics);
    }

    public function test_lecciones_iniciales_son_neutrales_y_bilingues(): void
    {
        self::assertCount(6, LessonService::SECTIONS);
        self::assertSame(200, LessonService::MAX_WORDS);

        $seed = (string) file_get_contents(dirname(__DIR__, 2) . '/sql/seed.sql');
        $lessonPart = substr($seed, (int) strpos($seed, '-- Lecciones contextuales iniciales'));

        self::assertStringNotContainsStringIgnoringCase('deberías', $lessonPart);
        self::assertStringNotContainsStringIgnoringCase('deberia', $lessonPart);
        self::assertStringNotContainsStringIgnoringCase('should', $lessonPart);
        self::assertStringContainsString("'What is saving?'", $lessonPart);
        self::assertStringContainsString("'¿Qué es el ahorro?'", $lessonPart);
    }
}
