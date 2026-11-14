<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\MethodRecommender;
use PHPUnit\Framework\TestCase;

final class MethodRecommenderTest extends TestCase
{
    private MethodRecommender $recommender;

    protected function setUp(): void
    {
        $this->recommender = new MethodRecommender();
    }

    public function test_variable_income_with_savings_goal_recommends_envelopes(): void
    {
        $result = $this->recommender->recommend([
            'p1' => 'variable',
            'p2' => 'no',
            'p3' => 'ahorrar',
        ]);

        self::assertSame('seis_sobres', $result);
    }

    public function test_debt_priority_recommends_70_20_10(): void
    {
        $result = $this->recommender->recommend([
            'p1' => 'fijo',
            'p2' => 'si',
            'p3' => 'invertir',
        ]);

        self::assertSame('70_20_10', $result);
    }

    public function test_spending_freedom_recommends_50_30_20(): void
    {
        $result = $this->recommender->recommend([
            'p1' => 'fijo',
            'p2' => 'no',
            'p3' => 'libertad',
        ]);

        self::assertSame('50_30_20', $result);
    }

    public function test_giving_goal_recommends_five_jars(): void
    {
        $result = $this->recommender->recommend([
            'p1' => 'mixto',
            'p2' => 'no_seguro',
            'p3' => 'donar',
        ]);

        self::assertSame('cinco_frascos', $result);
    }

    public function test_empty_answers_fall_back_to_catalog_order(): void
    {
        self::assertSame('50_30_20', $this->recommender->recommend([]));
    }
}
