<?php

namespace Database\Seeders;

use App\Services\FormatTemplateService;
use Illuminate\Database\Seeder;

class DefaultFormatTemplatesSeeder extends Seeder
{
    public function run(FormatTemplateService $service): void
    {
        $service->seedDefaults();
    }
}
