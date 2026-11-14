<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\RecurrenceService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class RecurrenceServiceTest extends TestCase
{
    private RecurrenceService $service;

    private DateTimeZone $timezone;

    protected function setUp(): void
    {
        $this->service = new RecurrenceService();
        $this->timezone = new DateTimeZone('America/Mexico_City');
    }

    public function test_diaria_suma_un_dia(): void
    {
        $next = $this->service->next($this->date('2026-10-20 09:00:00'), 'diaria', $this->timezone);

        self::assertSame('2026-10-21 09:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_semanal_suma_siete_dias(): void
    {
        $next = $this->service->next($this->date('2026-10-20 09:00:00'), 'semanal', $this->timezone);

        self::assertSame('2026-10-27 09:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_mensual_ajusta_dia_31(): void
    {
        $next = $this->service->next($this->date('2026-01-31 09:00:00'), 'mensual', $this->timezone);

        self::assertSame('2026-02-28 09:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_mensual_en_bisiesto(): void
    {
        $next = $this->service->next($this->date('2028-01-31 09:00:00'), 'mensual', $this->timezone);

        self::assertSame('2028-02-29 09:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_anual_ajusta_29_de_febrero(): void
    {
        $next = $this->service->next($this->date('2028-02-29 09:00:00'), 'anual', $this->timezone);

        self::assertSame('2029-02-28 09:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_ocurrencias_vencidas_en_orden(): void
    {
        $dates = $this->service->dueDates(
            $this->date('2026-10-01 09:00:00'),
            $this->date('2026-10-03 12:00:00'),
            'diaria',
            $this->timezone
        );

        self::assertCount(3, $dates);
        self::assertSame('2026-10-01', $dates[0]->format('Y-m-d'));
        self::assertSame('2026-10-02', $dates[1]->format('Y-m-d'));
        self::assertSame('2026-10-03', $dates[2]->format('Y-m-d'));
    }

    public function test_ocurrencias_respetan_el_tope(): void
    {
        $dates = $this->service->dueDates(
            $this->date('2020-01-01 09:00:00'),
            $this->date('2026-10-03 12:00:00'),
            'diaria',
            $this->timezone,
            5
        );

        self::assertCount(5, $dates);
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, $this->timezone);
    }
}
