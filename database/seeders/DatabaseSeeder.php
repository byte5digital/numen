<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,      // Must run first — other seeders may assign roles
            DemoSeeder::class,
            PageSeeder::class,
            ContentBlockSeeder::class,
            BlogPostSeeder::class,
            TaxonomySeeder::class,
        ]);
    }
}
