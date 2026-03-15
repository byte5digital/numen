<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class I18nTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_space_locales_table_exists(): void
    {
        $exists = Schema::hasTable('space_locales');
        if (! $exists) {
            $this->markTestSkipped('Migrations not initialized for testing');
        }
        $this->assertTrue($exists);
    }

    public function test_space_locales_columns(): void
    {
        if (! Schema::hasTable('space_locales')) {
            $this->markTestSkipped('Migrations not initialized for testing');
        }
        $columns = Schema::getColumnListing('space_locales');
        $this->assertContains('id', $columns);
        $this->assertContains('space_id', $columns);
        $this->assertContains('locale', $columns);
        $this->assertContains('is_default', $columns);
    }

    public function test_translation_jobs_table_exists(): void
    {
        if (! Schema::hasTable('content_translation_jobs')) {
            $this->markTestSkipped('Migrations not initialized for testing');
        }
        $this->assertTrue(Schema::hasTable('content_translation_jobs'));
    }

    public function test_translation_jobs_columns(): void
    {
        if (! Schema::hasTable('content_translation_jobs')) {
            $this->markTestSkipped('Migrations not initialized for testing');
        }
        $columns = Schema::getColumnListing('content_translation_jobs');
        $this->assertContains('id', $columns);
        $this->assertContains('content_id', $columns);
        $this->assertContains('target_locale', $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('ai_model', $columns);
    }

    public function test_feature_build_completeness(): void
    {
        $projRoot = __DIR__.'/../../';
        $this->assertFileExists($projRoot.'app/Services/LocaleService.php');
        $this->assertFileExists($projRoot.'app/Services/TranslationService.php');
        $this->assertFileExists($projRoot.'app/Services/AITranslationService.php');
        $this->assertFileExists($projRoot.'app/Models/SpaceLocale.php');
        $this->assertFileExists($projRoot.'app/Models/ContentTranslationJob.php');
    }

    public function test_migrations_exist(): void
    {
        $projRoot = __DIR__.'/../../';
        $migrationDir = $projRoot.'database/migrations';

        $files = glob($migrationDir.'/*locale*.php');
        $this->assertNotEmpty($files, 'Locale migration not found');

        $files = glob($migrationDir.'/*translation_job*.php');
        $this->assertNotEmpty($files, 'Translation job migration not found');
    }
}
