<?php

namespace Tests\Unit\Quality;

use App\Services\Quality\QualityDimensionResult;
use PHPUnit\Framework\TestCase;

class QualityDimensionResultTest extends TestCase
{
    public function test_make_clamps_score(): void
    {
        $this->assertSame(100.0, QualityDimensionResult::make(150)->getScore());
        $this->assertSame(0.0, QualityDimensionResult::make(-10)->getScore());
    }

    public function test_stores_items_and_metadata(): void
    {
        $r = QualityDimensionResult::make(75.0, [['type' => 'info', 'message' => 'ok']], ['wc' => 100]);
        $this->assertSame(75.0, $r->getScore());
        $this->assertCount(1, $r->getItems());
        $this->assertSame(100, $r->getMetadata()['wc']);
    }

    public function test_count_by_type(): void
    {
        $r = QualityDimensionResult::make(50.0, [
            ['type' => 'info', 'message' => 'a'],
            ['type' => 'warning', 'message' => 'b'],
            ['type' => 'error', 'message' => 'c'],
        ]);
        $this->assertSame(1, $r->countByType('info'));
        $this->assertSame(1, $r->countByType('warning'));
        $this->assertSame(1, $r->countByType('error'));
    }
}
