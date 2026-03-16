<?php

namespace Database\Seeders;

use App\Models\Space;
use Illuminate\Database\Seeder;

class DefaultSpaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Space::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default', 'default_locale' => 'en']
        );
    }
}
