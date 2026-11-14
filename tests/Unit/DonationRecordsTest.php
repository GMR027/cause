<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\DonationService;
use PHPUnit\Framework\TestCase;

final class DonationRecordsTest extends TestCase
{
    public function testFirstDonationIsOnlyFirst(): void
    {
        $this->assertSame(['first'], DonationService::records(['count' => 0, 'max' => 0, 'months' => []], 5000, '2026-11'));
    }

    public function testBiggestDonationBeatsPreviousMax(): void
    {
        $before = ['count' => 2, 'max' => 10000, 'months' => ['2026-11' => 15000]];

        $this->assertSame(['biggest'], DonationService::records($before, 10001, '2026-11'));
        $this->assertSame([], DonationService::records($before, 10000, '2026-11'));
    }

    public function testBestMonthOnlyWhenCrossingPreviousBest(): void
    {
        $before = ['count' => 3, 'max' => 50000, 'months' => ['2026-10' => 50000, '2026-11' => 40000]];

        $this->assertSame(['best_month'], DonationService::records($before, 20000, '2026-11'));
        $this->assertSame([], DonationService::records($before, 5000, '2026-11'));

        // Ya superado este mes: no se repite.
        $after = ['count' => 5, 'max' => 50000, 'months' => ['2026-10' => 50000, '2026-11' => 60000]];
        $this->assertSame([], DonationService::records($after, 1000, '2026-11'));
    }

    public function testFirstMonthHasNoBestMonthRecord(): void
    {
        $before = ['count' => 1, 'max' => 9000, 'months' => ['2026-11' => 9000]];

        $this->assertSame([], DonationService::records($before, 1000, '2026-11'));
    }

    public function testCountMilestones(): void
    {
        $before = ['count' => 9, 'max' => 90000, 'months' => ['2026-11' => 90000]];

        $this->assertSame(['milestone'], DonationService::records($before, 100, '2026-11'));
    }
}
