<?php

namespace Tests\Unit;

use App\Models\Space;
use App\Services\AI\CostTracker;
use App\Services\AI\ImageGenerator;
use App\Services\AI\ImagePromptBuilder;
use App\Services\AI\LLMManager;
use App\Services\AI\LLMResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // --- Graceful failure when no API key ---

    public function test_throws_when_api_key_not_configured(): void
    {
        config(['numen.providers.openai.api_key' => '']);

        $generator = app(ImageGenerator::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API key not configured');

        $generator->generate('A professional hero banner', 'test-space-id');
    }

    // --- DALL-E API mock ---

    public function test_generates_image_and_creates_media_asset(): void
    {
        config(['numen.providers.openai.api_key'  => 'sk-test-key-123']);
        config(['numen.providers.openai.base_url' => 'https://api.openai.com/v1']);

        $space = Space::factory()->create();

        // Mock the DALL-E 3 API response
        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [
                    [
                        'url'             => 'https://example.com/generated-image.png',
                        'revised_prompt'  => 'A professional corporate hero banner',
                    ],
                ],
            ], 200),
            // Mock the image download
            'example.com/generated-image.png' => Http::response(
                str_repeat('x', 1024), // fake image bytes
                200,
            ),
        ]);

        $costTracker = $this->createMock(CostTracker::class);
        $costTracker->method('recordUsage')->willReturn(true);
        $this->app->instance(CostTracker::class, $costTracker);

        $generator = app(ImageGenerator::class);
        $asset     = $generator->generate('A corporate hero banner', $space->id);

        $this->assertEquals($space->id, $asset->space_id);
        $this->assertEquals('ai_generated', $asset->source);
        $this->assertEquals('image/png', $asset->mime_type);
        $this->assertStringContainsString('media/' . $space->id, $asset->path);
    }

    public function test_throws_on_dalle_api_error(): void
    {
        config(['numen.providers.openai.api_key'  => 'sk-test-key-123']);
        config(['numen.providers.openai.base_url' => 'https://api.openai.com/v1']);

        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response([
                'error' => ['message' => 'Content policy violation'],
            ], 400),
        ]);

        $space     = Space::factory()->create();
        $generator = app(ImageGenerator::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DALL-E 3 API error');

        $generator->generate('Inappropriate prompt', $space->id);
    }

    public function test_throws_when_no_image_url_returned(): void
    {
        config(['numen.providers.openai.api_key'  => 'sk-test-key-123']);
        config(['numen.providers.openai.base_url' => 'https://api.openai.com/v1']);

        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['revised_prompt' => 'something', /* no 'url' key */]],
            ], 200),
        ]);

        $space     = Space::factory()->create();
        $generator = app(ImageGenerator::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DALL-E 3 returned no image URL');

        $generator->generate('A test prompt', $space->id);
    }

    // --- ImagePromptBuilder ---

    public function test_prompt_builder_returns_llm_generated_prompt(): void
    {
        $llm = $this->createMock(LLMManager::class);
        $llm->method('complete')->willReturn(new LLMResponse(
            content:      'A stunning corporate hero image with blue tones',
            model:        'claude-haiku-4-5-20251001',
            provider:     'anthropic',
            inputTokens:  50,
            outputTokens: 30,
            costUsd:      0.0001,
            latencyMs:    100,
        ));

        $this->app->instance(LLMManager::class, $llm);

        $builder = app(ImagePromptBuilder::class);
        $prompt  = $builder->build('Laravel Best Practices', 'Modern framework overview', ['laravel', 'php']);

        $this->assertEquals('A stunning corporate hero image with blue tones', $prompt);
    }

    public function test_prompt_builder_falls_back_when_llm_fails(): void
    {
        $llm = $this->createMock(LLMManager::class);
        $llm->method('complete')->willThrowException(new \RuntimeException('LLM unavailable'));

        $this->app->instance(LLMManager::class, $llm);

        $builder = app(ImagePromptBuilder::class);
        $prompt  = $builder->build('Test Article');

        // Should return fallback prompt (not throw)
        $this->assertNotEmpty($prompt);
        $this->assertStringContainsString('Test Article', $prompt);
    }

    public function test_prompt_builder_falls_back_when_llm_returns_empty(): void
    {
        $llm = $this->createMock(LLMManager::class);
        $llm->method('complete')->willReturn(new LLMResponse(
            content:      '',  // empty response
            model:        'claude-haiku-4-5-20251001',
            provider:     'anthropic',
            inputTokens:  50,
            outputTokens: 0,
            costUsd:      0.0,
            latencyMs:    100,
        ));

        $this->app->instance(LLMManager::class, $llm);

        $builder = app(ImagePromptBuilder::class);
        $prompt  = $builder->build('My Title', null, [], 'blog_post');

        $this->assertNotEmpty($prompt);
        $this->assertStringContainsString('My Title', $prompt);
    }

    public function test_prompt_builder_includes_title_in_fallback(): void
    {
        $llm = $this->createMock(LLMManager::class);
        $llm->method('complete')->willThrowException(new \RuntimeException('Error'));

        $this->app->instance(LLMManager::class, $llm);

        $builder = app(ImagePromptBuilder::class);
        $prompt  = $builder->build('Advanced PHP Patterns');

        $this->assertStringContainsString('Advanced PHP Patterns', $prompt);
    }
}
