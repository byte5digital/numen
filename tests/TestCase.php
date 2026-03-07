<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Run the RoleSeeder before each test that uses RefreshDatabase.
     *
     * This ensures the 4 built-in roles (admin, editor, author, viewer) exist
     * so that User::factory()->admin() and other factory states work correctly.
     */
    protected bool $seed = true;

    /** @var class-string<\Illuminate\Database\Seeder> */
    protected string $seeder = RoleSeeder::class;
}
