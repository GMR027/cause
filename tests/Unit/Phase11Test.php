<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\CurrencyService;
use Cauce\Services\ExchangeRateService;
use PHPUnit\Framework\TestCase;

final class Phase11Test extends TestCase
{
    public function test_catalogo_de_monedas(): void
    {
        self::assertSame(['MXN', 'USD', 'EUR'], CurrencyService::SUPPORTED);
    }

    public function test_conversion_y_redondeo(): void
    {
        self::assertSame(175000, ExchangeRateService::applyRate(10000, 17.5));
        self::assertSame(0, ExchangeRateService::applyRate(1, 0.4));
        self::assertSame(1, ExchangeRateService::applyRate(1, 0.5));
        self::assertSame(10000, ExchangeRateService::applyRate(10000, 1.0));
        self::assertSame(0, ExchangeRateService::applyRate(0, 17.5));
    }
}
