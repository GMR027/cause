<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\DebtService;
use Cauce\Services\SubscriptionService;
use PHPUnit\Framework\TestCase;

final class Phase9Test extends TestCase
{
    public function test_anualizacion_de_suscripciones(): void
    {
        self::assertSame(365.0, SubscriptionService::annualFactor('diaria'));
        self::assertSame(52.0, SubscriptionService::annualFactor('semanal'));
        self::assertSame(12.0, SubscriptionService::annualFactor('mensual'));
        self::assertSame(1.0, SubscriptionService::annualFactor('anual'));
        self::assertSame(12.0, SubscriptionService::annualFactor('desconocida'));
    }

    public function test_estrategias_de_deuda(): void
    {
        self::assertContains('bola_nieve', DebtService::STRATEGIES);
        self::assertContains('avalancha', DebtService::STRATEGIES);
        self::assertContains('manual', DebtService::STRATEGIES);
    }
}
