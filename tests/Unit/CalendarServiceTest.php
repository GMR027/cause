<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Helpers\I18n;
use Cauce\Services\CalendarService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

final class CalendarServiceTest extends TestCase
{
    private CalendarService $service;

    protected function setUp(): void
    {
        $this->service = new CalendarService($this->createMock(PDO::class));
    }

    public function test_es_inicia_semana_en_lunes(): void
    {
        $this->useLang('es');

        self::assertSame(1, $this->invoke('firstWeekday'));

        $weekdays = $this->invoke('weekdays', 1);
        self::assertSame('L', $weekdays[0]);
        self::assertSame('D', $weekdays[6]);
    }

    public function test_en_inicia_semana_en_domingo(): void
    {
        $this->useLang('en');

        self::assertSame(0, $this->invoke('firstWeekday'));

        $weekdays = $this->invoke('weekdays', 0);
        self::assertSame('S', $weekdays[0]);
        self::assertSame('S', $weekdays[6]);
    }

    public function test_mes_localizado(): void
    {
        $this->useLang('es');
        self::assertSame('octubre 2026', $this->invoke('monthLabel', '2026-10'));

        $this->useLang('en');
        self::assertSame('October 2026', $this->invoke('monthLabel', '2026-10'));
    }

    public function test_mes_invalido_usa_el_mes_de_hoy(): void
    {
        $this->useLang('es');

        self::assertSame('2026-10', $this->invoke('normalizeMonth', '2026-13', '2026-10-20'));
        self::assertSame('2026-10', $this->invoke('normalizeMonth', null, '2026-10-20'));
        self::assertSame('2026-09', $this->invoke('normalizeMonth', '2026-09', '2026-10-20'));
    }

    private function useLang(string $lang): void
    {
        $property = new ReflectionProperty(I18n::class, 'lang');
        $property->setValue(null, $lang);
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod(CalendarService::class, $method);

        return $reflection->invoke($this->service, ...$arguments);
    }
}
