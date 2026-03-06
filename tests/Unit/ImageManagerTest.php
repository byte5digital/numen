<?php

namespace Tests\Unit;

use App\Models\MediaAsset;
use App\Models\Space;
use App\Services\AI\CostTracker;
use App\Services\AI\ImageManager;
use App\Services\AI\ImageProviders\FalImageProvider;
use App\Services\AI\ImageProviders\ImageResult;
use App\Services\AI\ImageProviders\OpenAIImageProvider;
use App\Services\AI\ImageProviders\ReplicateImageProvider;
use App\Services\AI\ImageProviders\TogetherImageProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // --- Provider resolution ---

    public function test_uses_persona_provider_when_specified(): void
    {
        $space = Space::factory()->create();

        $openai = $this->createMock(OpenAIImageProvider::class);
        $openai->method('name')->willReturn('openai');
        $openai->method('isAvailable')->willReturn(true);
        $openai->method('generate')->willReturn(new ImageResult(
            imageData: str_repeat('x', 512),
            mimeType: 'image/png',
            model: 'gpt-image-1',
            provider: 'openai',
            costUsd: 0.04,
        ));

        $together = $this->createMock(TogetherImageProvider::class);
        $together->method('name')->willReturn('together');
        $together->method('isAvailable')->willReturn(false);

        $fal = $this->createMock(FalImageProvider::class);
        $fal->method('name')->willReturn('fal');
        $fal->method('isAvailable')->willReturn(false);

        $replicate = $this->createMock(ReplicateImageProvider::class);
        $replicate->method('name')->willReturn('replicate');
        $replicate->method('isAvailable')->willReturn(false);

        $costTracker = $this->createMock(CostTracker::class);
        $costTracker->method('recordUsage')->willReturn(true);

        $manager = new ImageManager($openai, $together, $fal, $replicate, $costTracker);

        $asset = $manager->generate(
            prompt: 'A test image',
            spaceId: $space->id,
            personaConfig: ['generator_provider' => 'openai'],
        );

        $this->assertInstanceOf(MediaAsset::class, $asset);
        $this->assertEquals('openai', $asset->ai_metadata['provider']);
        $this->assertEquals('ai_generated', $asset->source);
    }

    public function test_falls_back_to_config_default_when_persona_provider_unavailable(): void
    {
        $space = Space::factory()->create();
        config(['numen.default_image_provider' => 'together']);

        $openai = $this->createMock(OpenAIImageProvider::class);
        $openai->method('name')->willReturn('openai');
        $openai->method('isAvailable')->willReturn(false);

        $together = $this->createMock(TogetherImageProvider::class);
        $together->method('name')->willReturn('together');
        $together->method('isAvailable')->willReturn(true);
        $together->method('generate')->willReturn(new ImageResult(
            imageData: str_repeat('y', 512),
            mimeType: 'image/png',
            model: 'black-forest-labs/FLUX.1-schnell',
            provider: 'together',
            costUsd: 0.003,
        ));

        $fal = $this->createMock(FalImageProvider::class);
        $fal->method('name')->willReturn('fal');
        $fal->method('isAvailable')->willReturn(false);

        $replicate = $this->createMock(ReplicateImageProvider::class);
        $replicate->method('name')->willReturn('replicate');
        $replicate->method('isAvailable')->willReturn(false);

        $costTracker = $this->createMock(CostTracker::class);
        $costTracker->method('recordUsage')->willReturn(true);

        $manager = new ImageManager($openai, $together, $fal, $replicate, $costTracker);

        // persona_provider is openai (unavailable) → should fall back to together (default)
        $asset = $manager->generate(
            prompt: 'A test image',
            spaceId: $space->id,
            personaConfig: ['generator_provider' => 'openai'],
        );

        $this->assertEquals('together', $asset->ai_metadata['provider']);
    }

    public function test_throws_when_no_provider_available(): void
    {
        $space = Space::factory()->create();

        $makeUnavailable = function (string $name): object {
            $mock = $this->createMock(OpenAIImageProvider::class);
            $mock->method('name')->willReturn($name);
            $mock->method('isAvailable')->willReturn(false);

            return $mock;
        };

        $costTracker = $this->createMock(CostTracker::class);

        $manager = new ImageManager(
            $makeUnavailable('openai'),
            $makeUnavailable('together'),
            $makeUnavailable('fal'),
            $makeUnavailable('replicate'),
            $costTracker,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No image provider is available');

        $manager->generate('test', $space->id);
    }

    public function test_has_available_provider_returns_true_when_any_available(): void
    {
        $openai = $this->createMock(OpenAIImageProvider::class);
        $openai->method('name')->willReturn('openai');
        $openai->method('isAvailable')->willReturn(false);

        $together = $this->createMock(TogetherImageProvider::class);
        $together->method('name')->willReturn('together');
        $together->method('isAvailable')->willReturn(true);

        $fal = $this->createMock(FalImageProvider::class);
        $fal->method('name')->willReturn('fal');
        $fal->method('isAvailable')->willReturn(false);

        $replicate = $this->createMock(ReplicateImageProvider::class);
        $replicate->method('name')->willReturn('replicate');
        $replicate->method('isAvailable')->willReturn(false);

        $costTracker = $this->createMock(CostTracker::class);

        $manager = new ImageManager($openai, $together, $fal, $replicate, $costTracker);
        $this->assertTrue($manager->hasAvailableProvider());
    }

    public function test_has_available_provider_returns_false_when_none_available(): void
    {
        $makeUnavailable = function (string $name): object {
            $mock = $this->createMock(OpenAIImageProvider::class);
            $mock->method('name')->willReturn($name);
            $mock->method('isAvailable')->willReturn(false);

            return $mock;
        };

        $costTracker = $this->createMock(CostTracker::class);

        $manager = new ImageManager(
            $makeUnavailable('openai'),
            $makeUnavailable('together'),
            $makeUnavailable('fal'),
            $makeUnavailable('replicate'),
            $costTracker,
        );

        $this->assertFalse($manager->hasAvailableProvider());
    }

    public function test_saves_image_to_storage_and_creates_asset(): void
    {
        $space = Space::factory()->create();
        $fakeBytes = str_repeat('IMG', 200);

        $openai = $this->createMock(OpenAIImageProvider::class);
        $openai->method('name')->willReturn('openai');
        $openai->method('isAvailable')->willReturn(true);
        $openai->method('generate')->willReturn(new ImageResult(
            imageData: $fakeBytes,
            mimeType: 'image/png',
            model: 'gpt-image-1',
            provider: 'openai',
            revisedPrompt: 'Revised: a corporate image',
            costUsd: 0.04,
        ));

        $together = $this->createMock(TogetherImageProvider::class);
        $together->method('name')->willReturn('together');
        $together->method('isAvailable')->willReturn(false);

        $fal = $this->createMock(FalImageProvider::class);
        $fal->method('name')->willReturn('fal');
        $fal->method('isAvailable')->willReturn(false);

        $replicate = $this->createMock(ReplicateImageProvider::class);
        $replicate->method('name')->willReturn('replicate');
        $replicate->method('isAvailable')->willReturn(false);

        $costTracker = $this->createMock(CostTracker::class);
        $costTracker->expects($this->once())->method('recordUsage')->with(0.04, $space->id);

        $manager = new ImageManager($openai, $together, $fal, $replicate, $costTracker);

        $asset = $manager->generate('A hero banner', $space->id);

        // MediaAsset created
        $this->assertDatabaseHas('media_assets', [
            'space_id' => $space->id,
            'source' => 'ai_generated',
            'mime_type' => 'image/png',
            'disk' => 'public',
        ]);

        // File on disk
        Storage::disk('public')->assertExists($asset->path);

        // Metadata
        $this->assertEquals('gpt-image-1', $asset->ai_metadata['model']);
        $this->assertEquals('openai', $asset->ai_metadata['provider']);
        $this->assertEquals('Revised: a corporate image', $asset->ai_metadata['revised_prompt']);
        $this->assertEquals(0.04, $asset->ai_metadata['cost_usd']);
    }

    public function test_replicate_provider_is_registered(): void
    {
        $openai = $this->createMock(OpenAIImageProvider::class);
        $openai->method('name')->willReturn('openai');
        $openai->method('isAvailable')->willReturn(false);

        $together = $this->createMock(TogetherImageProvider::class);
        $together->method('name')->willReturn('together');
        $together->method('isAvailable')->willReturn(false);

        $fal = $this->createMock(FalImageProvider::class);
        $fal->method('name')->willReturn('fal');
        $fal->method('isAvailable')->willReturn(false);

        $space = Space::factory()->create();
        $fakeBytes = str_repeat('R', 200);

        $replicate = $this->createMock(ReplicateImageProvider::class);
        $replicate->method('name')->willReturn('replicate');
        $replicate->method('isAvailable')->willReturn(true);
        $replicate->method('generate')->willReturn(new ImageResult(
            imageData: $fakeBytes,
            mimeType: 'image/png',
            model: 'black-forest-labs/flux-2-max',
            provider: 'replicate',
            costUsd: 0.05,
        ));

        $costTracker = $this->createMock(CostTracker::class);
        $costTracker->method('recordUsage')->willReturn(true);

        config(['numen.default_image_provider' => 'replicate']);

        $manager = new ImageManager($openai, $together, $fal, $replicate, $costTracker);
        $asset = $manager->generate('A test image', $space->id);

        $this->assertEquals('replicate', $asset->ai_metadata['provider']);
        $this->assertEquals('black-forest-labs/flux-2-max', $asset->ai_metadata['model']);
    }
}
