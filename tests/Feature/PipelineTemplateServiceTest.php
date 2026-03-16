<?php

namespace Tests\Feature;

use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateVersion;
use App\Models\Space;
use App\Services\PipelineTemplates\PipelineTemplateService;
use Database\Seeders\BuiltInTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class PipelineTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private PipelineTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(PipelineTemplateService::class);
    }

    /** @return array<string, mixed> */
    private function validDefinition(): array
    {
        return [
            'version' => '1.0',
            'personas' => [
                ['ref' => 'writer', 'name' => 'Writer', 'system_prompt' => 'You are a writer.', 'llm_provider' => 'openai', 'llm_model' => 'gpt-4o'],
            ],
            'stages' => [
                ['type' => 'ai_generate', 'name' => 'Draft', 'persona_ref' => 'writer', 'config' => ['prompt_template' => 'Write about {topic}.'], 'enabled' => true],
            ],
            'settings' => ['auto_publish' => false, 'review_required' => true, 'max_retries' => 2, 'timeout_seconds' => 120],
        ];
    }

    public function test_create_makes_template_with_space(): void
    {
        $space = Space::factory()->create();
        $template = $this->service->create($space, ['name' => 'My Template', 'description' => 'A test', 'category' => 'content']);

        $this->assertInstanceOf(PipelineTemplate::class, $template);
        $this->assertEquals($space->id, $template->space_id);
        $this->assertEquals('My Template', $template->name);
        $this->assertEquals('my-template', $template->slug);
        $this->assertFalse($template->is_published);
    }

    public function test_create_generates_unique_slug_on_collision(): void
    {
        $space = Space::factory()->create();
        $t1 = $this->service->create($space, ['name' => 'Duplicate']);
        $t2 = $this->service->create($space, ['name' => 'Duplicate']);
        $this->assertNotEquals($t1->slug, $t2->slug);
        $this->assertEquals('duplicate', $t1->slug);
        $this->assertEquals('duplicate-1', $t2->slug);
    }

    public function test_update_changes_fields(): void
    {
        $space = Space::factory()->create();
        $template = $this->service->create($space, ['name' => 'Original']);
        $updated = $this->service->update($template, ['name' => 'Updated Name', 'description' => 'Updated', 'category' => 'marketing']);
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('Updated', $updated->description);
        $this->assertEquals('marketing', $updated->category);
    }

    public function test_delete_soft_deletes_template(): void
    {
        $space = Space::factory()->create();
        $template = $this->service->create($space, ['name' => 'To Delete']);
        $this->service->delete($template);
        $this->assertSoftDeleted('pipeline_templates', ['id' => $template->id]);
    }

    public function test_publish_sets_is_published_and_clears_space_id(): void
    {
        $space = Space::factory()->create();
        $template = $this->service->create($space, ['name' => 'To Publish']);
        $this->service->publish($template);
        $template->refresh();
        $this->assertTrue($template->is_published);
        $this->assertNull($template->space_id);
    }

    public function test_unpublish_clears_is_published(): void
    {
        $space = Space::factory()->create();
        $template = $this->service->create($space, ['name' => 'To Unpublish']);
        $this->service->publish($template);
        $this->service->unpublish($template);
        $template->refresh();
        $this->assertFalse($template->is_published);
    }

    public function test_create_version_stores_version_as_latest(): void
    {
        $space = Space::factory()->create();
        $template = $this->service->create($space, ['name' => 'Versioned']);
        $version = $this->service->createVersion($template, $this->validDefinition(), '1.0.0', 'First release');
        $this->assertInstanceOf(PipelineTemplateVersion::class, $version);
        $this->assertTrue($version->is_latest);
        $this->assertEquals('1.0.0', $version->version);
        $this->assertEquals('First release', $version->changelog);
    }

    public function test_create_version_unsets_previous_latest(): void
    {
        $space = Space::factory()->create();
        $template = $this->service->create($space, ['name' => 'Multi-version']);
        $v1 = $this->service->createVersion($template, $this->validDefinition(), '1.0.0');
        $v2 = $this->service->createVersion($template, $this->validDefinition(), '1.1.0');
        $this->assertFalse($v1->refresh()->is_latest);
        $this->assertTrue($v2->refresh()->is_latest);
    }

    public function test_create_version_rejects_invalid_definition(): void
    {
        $space = Space::factory()->create();
        $template = $this->service->create($space, ['name' => 'Bad Version']);
        $this->expectException(InvalidArgumentException::class);
        $this->service->createVersion($template, ['version' => '1.0'], '1.0.0');
    }

    public function test_create_version_validates_persona_refs(): void
    {
        $space = Space::factory()->create();
        $template = $this->service->create($space, ['name' => 'Bad Ref']);
        $badDef = $this->validDefinition();
        $badDef['stages'][0]['persona_ref'] = 'nonexistent_persona';
        $this->expectException(InvalidArgumentException::class);
        $this->service->createVersion($template, $badDef, '1.0.0');
    }

    public function test_export_returns_serializable_array(): void
    {
        $space = Space::factory()->create();
        $template = $this->service->create($space, ['name' => 'Exportable', 'description' => 'desc']);
        $this->service->createVersion($template, $this->validDefinition(), '1.2.3', 'changelog');
        $exported = $this->service->export($template);
        $this->assertArrayHasKey('numen_export', $exported);
        $this->assertArrayHasKey('template', $exported);
        $this->assertArrayHasKey('version', $exported);
        $this->assertEquals('Exportable', $exported['template']['name']);
        $this->assertEquals('1.2.3', $exported['version']['version']);
        $this->assertNotFalse(json_encode($exported));
    }

    public function test_import_creates_template_and_version(): void
    {
        $space = Space::factory()->create();
        $data = [
            'numen_export' => '1.0',
            'template' => ['name' => 'Imported Template', 'description' => 'Imported', 'category' => 'content'],
            'version' => ['version' => '2.0.0', 'changelog' => 'Imported', 'definition' => $this->validDefinition()],
        ];
        $imported = $this->service->import($space, $data);
        $this->assertEquals('Imported Template', $imported->name);
        $this->assertEquals($space->id, $imported->space_id);
        $this->assertCount(1, $imported->versions);
        $this->assertEquals('2.0.0', $imported->versions->first()->version);
    }

    public function test_export_import_roundtrip(): void
    {
        $space = Space::factory()->create();
        $original = $this->service->create($space, ['name' => 'Roundtrip Template', 'category' => 'test']);
        $this->service->createVersion($original, $this->validDefinition(), '3.0.0', 'Round trip');
        $exported = $this->service->export($original);
        $space2 = Space::factory()->create();
        $imported = $this->service->import($space2, $exported);
        $this->assertEquals('Roundtrip Template', $imported->name);
        $this->assertEquals('3.0.0', $imported->versions->first()->version);
    }

    public function test_export_to_file_creates_json_file(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create();
        $template = $this->service->create($space, ['name' => 'File Export']);
        $this->service->createVersion($template, $this->validDefinition(), '1.0.0');
        $path = $this->service->exportToFile($template);
        $this->assertFileExists($path);
        /** @var array<string, mixed> $content */
        $content = json_decode((string) file_get_contents($path), true);
        $this->assertEquals('File Export', $content['template']['name']);
    }

    public function test_import_from_file_creates_template(): void
    {
        Storage::fake('local');
        $space = Space::factory()->create();
        $template = $this->service->create($space, ['name' => 'Source Template']);
        $this->service->createVersion($template, $this->validDefinition(), '1.0.0');
        $path = $this->service->exportToFile($template);
        $space2 = Space::factory()->create();
        $imported = $this->service->importFromFile($space2, $path);
        $this->assertEquals('Source Template', $imported->name);
    }

    public function test_import_throws_without_template_key(): void
    {
        $space = Space::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        $this->service->import($space, ['numen_export' => '1.0']);
    }

    public function test_seeder_creates_all_8_built_in_templates(): void
    {
        $this->seed(BuiltInTemplateSeeder::class);
        $slugs = ['blog-post-pipeline', 'social-media-campaign', 'product-description', 'email-newsletter', 'press-release', 'landing-page', 'technical-documentation', 'video-script'];
        foreach ($slugs as $slug) {
            $this->assertDatabaseHas('pipeline_templates', ['slug' => $slug]);
        }
        $this->assertEquals(8, PipelineTemplate::whereNull('space_id')->where('is_published', true)->count());
    }

    public function test_seeder_creates_versions_for_all_templates(): void
    {
        $this->seed(BuiltInTemplateSeeder::class);
        $versionCount = PipelineTemplateVersion::whereHas('template', fn ($q) => $q->whereNull('space_id'))->where('is_latest', true)->count();
        $this->assertEquals(8, $versionCount);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(BuiltInTemplateSeeder::class);
        $this->seed(BuiltInTemplateSeeder::class);
        $this->assertEquals(8, PipelineTemplate::whereNull('space_id')->count());
    }

    public function test_seeder_templates_have_valid_definitions(): void
    {
        $this->seed(BuiltInTemplateSeeder::class);
        $validator = $this->app->make(\App\Services\PipelineTemplates\TemplateSchemaValidator::class);
        PipelineTemplateVersion::whereHas('template', fn ($q) => $q->whereNull('space_id'))
            ->get()
            ->each(function (PipelineTemplateVersion $v) use ($validator): void {
                $result = $validator->validate($v->definition);
                $this->assertTrue($result->isValid(), "Template {$v->template_id} invalid: ".implode('; ', $result->errors()));
            });
    }
}
