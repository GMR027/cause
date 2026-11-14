<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\MoneyFxService;
use PHPUnit\Framework\TestCase;

final class MoneyFxServiceTest extends TestCase
{
    public function test_nivel_segun_proporcion_del_ingreso(): void
    {
        $income = 2000000; // 20,000.00

        self::assertSame(1, MoneyFxService::level(10000, $income));   // 0.5 %
        self::assertSame(2, MoneyFxService::level(40000, $income));   // 2 %
        self::assertSame(3, MoneyFxService::level(100000, $income));  // 5 %
        self::assertSame(4, MoneyFxService::level(300000, $income));  // 15 %
        self::assertSame(5, MoneyFxService::level(700000, $income));  // 35 %
        self::assertSame(5, MoneyFxService::level(9000000, $income));
    }

    public function test_nivel_sin_ingreso_usa_umbrales_absolutos(): void
    {
        self::assertSame(1, MoneyFxService::level(10000, 0));
        self::assertSame(2, MoneyFxService::level(50000, 0));
        self::assertSame(3, MoneyFxService::level(200000, 0));
        self::assertSame(4, MoneyFxService::level(1000000, 0));
        self::assertSame(5, MoneyFxService::level(5000000, 0));
        self::assertSame(1, MoneyFxService::level(-500, 0));
    }
}
