<?php

namespace Tests\Unit\Quality;

use App\Services\Quality\QualityDimensionResult;
use PHPUnit\Framework\TestCase;

class QualityDimensionResultTest extends TestCase
{
    public function test_make_clamps_score_to_0_100(): void
    {
        $above = QualityDimensionResult::make(150);
        $below = QualityDimensionResult::make(-10);
        $this->assertSame(100.0, $above->getScore());
        $this->assertSame(0.0, $below->getScore());
    }

    public function test_make_stores_items_and_metadata(): void
    {
        $items = [['type' => 'info', 'message' => 'ok']];
        $meta  = ['word_count' => 100];
        $r     = QualityDimensionResult::make(75.0, $items, $meta);
        $this->assertSame(75.0, $r->getScore());
        $this->assertCount(1, $r->getItems());
        $this->assertSame(100, $r->getMetadata()['word_count']);
    }

    public function test_count_by_type(): void
    {
        $items = [
            ['type' => 'info',    'message' => 'a'],
            ['type' => 'warning', 'message' => 'b'],
            ['type' => 'warning', 'message' => 'c'],
            ['type' => 'error',   'message' => 'd'],
        ];
        $r = QualityDimensionResult::make(50.0, $items);
        $this->assertSame(1, $r->countByType('info'));
        $this->assertSame(2, $r->countByType('warning'));
        $this->assertSame(1, $r->countByType('error'));
    }
}
