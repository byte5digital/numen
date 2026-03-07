<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug(): void
    {
        echo 'roles exists: '.(Schema::hasTable('roles') ? 'YES' : 'NO')."\n";
        $this->assertTrue(Schema::hasTable('roles'));
    }
}
