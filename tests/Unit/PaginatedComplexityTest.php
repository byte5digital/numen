<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\GraphQL\Complexity\PaginatedComplexity;
use PHPUnit\Framework\TestCase;

class PaginatedComplexityTest extends TestCase
{
    private PaginatedComplexity $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PaginatedComplexity;
    }

    public function test_calculates_complexity_correctly(): void
    {
        // childComplexity=5, first=10 → 5 * 10 = 50
        $result = ($this->resolver)(5, ['first' => 10]);
        $this->assertEquals(50, $result);
    }

    public function test_default_complexity_for_no_args(): void
    {
        // No 'first' arg → defaults to 20
        $result = ($this->resolver)(3, []);
        $this->assertEquals(60, $result); // 3 * 20 = 60
    }

    public function test_clamps_first_to_maximum_of_1000(): void
    {
        // first=5000 should be clamped to 1000
        $result = ($this->resolver)(2, ['first' => 5000]);
        $this->assertEquals(2000, $result); // 2 * 1000 = 2000
    }

    public function test_handles_zero_child_complexity(): void
    {
        $result = ($this->resolver)(0, ['first' => 100]);
        $this->assertEquals(0, $result);
    }

    public function test_handles_first_arg_as_string(): void
    {
        // Args from GraphQL may arrive as strings — cast should handle it
        $result = ($this->resolver)(4, ['first' => '5']);
        $this->assertEquals(20, $result); // 4 * 5 = 20
    }
}
