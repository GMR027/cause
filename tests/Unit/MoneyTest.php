<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_parsea_pesos_enteros(): void
    {
        self::assertSame(1000000, parse_money_cents('10000'));
    }

    public function test_parsea_miles_con_coma(): void
    {
        self::assertSame(1000000, parse_money_cents('10,000'));
    }

    public function test_parsea_decimal_con_punto(): void
    {
        self::assertSame(1000050, parse_money_cents('10,000.50'));
    }

    public function test_parsea_formato_mexicano(): void
    {
        self::assertSame(1000050, parse_money_cents('10.000,50'));
    }

    public function test_rechaza_texto(): void
    {
        self::assertNull(parse_money_cents('diez mil'));
    }
}
