<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimpleRoleTest extends TestCase {
    use RefreshDatabase;
    public function test_schema_has_roles(): void {
        $basePath = app()->basePath();
        $migPath = app()->databasePath('migrations');
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $tableNames = array_map(fn($t) => $t->name, $tables);
        $this->assertContains('roles', $tableNames, 
            "basePath=$basePath | migPath=$migPath");
    }
}
