<?php

namespace Tests\Unit;

use App\Services\AI\ImageProviders\ReplicateImageProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReplicateImageProviderTest extends TestCase
{
    public function test_is_unavailable_when_no_api_key(): void
    {
        config(['numen.image_providers.replicate.api_key' => '']);
        $provider = app(ReplicateImageProvider::class);
        $this->assertFalse($provider->isAvailable());
    }

    public function test_is_available_when_api_key_set(): void
    {
        config(['numen.image_providers.replicate.api_key' => 'r8_test_key']);
        $provider = app(ReplicateImageProvider::class);
        $this->assertTrue($provider->isAvailable());
    }

    public function test_name_is_replicate(): void
    {
        $provider = app(ReplicateImageProvider::class);
        $this->assertEquals('replicate', $provider->name());
    }

    public function test_throws_when_no_api_key_on_generate(): void
    {
        config(['numen.image_providers.replicate.api_key' => '']);
        $provider = app(ReplicateImageProvider::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Replicate API key not configured');

        $provider->generate('test prompt', '1024x1024', 'vivid', 'standard');
    }

    public function test_generates_image_via_polling(): void
    {
        config([
            'numen.image_providers.replicate.api_key' => 'r8_test_key',
            'numen.image_providers.replicate.base_url' => 'https://api.replicate.com/v1',
            'numen.image_providers.replicate.default_model' => 'black-forest-labs/flux-2-max',
        ]);

        $fakeImageBytes = str_repeat('IMG', 100);

        Http::fake([
            // Step 1: create prediction
            'api.replicate.com/v1/predictions' => Http::response([
                'id' => 'pred_abc123',
                'status' => 'starting',
                'model' => 'black-forest-labs/flux-2-max',
            ], 201),
            // Step 2: poll — returns succeeded immediately
            'api.replicate.com/v1/predictions/pred_abc123' => Http::response([
                'id' => 'pred_abc123',
                'status' => 'succeeded',
                'output' => ['https://cdn.replicate.com/output/image.png'],
            ], 200),
            // Step 3: download image
            'cdn.replicate.com/output/image.png' => Http::response($fakeImageBytes, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $provider = app(ReplicateImageProvider::class);
        $result = $provider->generate('A futuristic cityscape', '1792x1024', 'vivid', 'standard');

        $this->assertEquals('image/png', $result->mimeType);
        $this->assertEquals('black-forest-labs/flux-2-max', $result->model);
        $this->assertEquals('replicate', $result->provider);
        $this->assertEquals($fakeImageBytes, $result->imageData);
    }

    public function test_throws_when_prediction_fails(): void
    {
        config([
            'numen.image_providers.replicate.api_key' => 'r8_test_key',
            'numen.image_providers.replicate.base_url' => 'https://api.replicate.com/v1',
            'numen.image_providers.replicate.default_model' => 'black-forest-labs/flux-2-max',
        ]);

        Http::fake([
            'api.replicate.com/v1/predictions' => Http::response([
                'id' => 'pred_fail',
                'status' => 'starting',
            ], 201),
            'api.replicate.com/v1/predictions/pred_fail' => Http::response([
                'id' => 'pred_fail',
                'status' => 'failed',
                'error' => 'NSFW content detected',
            ], 200),
        ]);

        $provider = app(ReplicateImageProvider::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Replicate prediction failed');

        $provider->generate('Bad prompt', '1024x1024', 'vivid', 'standard');
    }

    public function test_throws_when_prediction_returns_no_output(): void
    {
        config([
            'numen.image_providers.replicate.api_key' => 'r8_test_key',
            'numen.image_providers.replicate.base_url' => 'https://api.replicate.com/v1',
            'numen.image_providers.replicate.default_model' => 'black-forest-labs/flux-2-max',
        ]);

        Http::fake([
            'api.replicate.com/v1/predictions' => Http::response([
                'id' => 'pred_noout',
                'status' => 'starting',
            ], 201),
            'api.replicate.com/v1/predictions/pred_noout' => Http::response([
                'id' => 'pred_noout',
                'status' => 'succeeded',
                'output' => null,
            ], 200),
        ]);

        $provider = app(ReplicateImageProvider::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('returned no output URL');

        $provider->generate('A test prompt', '1024x1024', 'vivid', 'standard');
    }

    public function test_handles_string_output_from_replicate(): void
    {
        config([
            'numen.image_providers.replicate.api_key' => 'r8_test_key',
            'numen.image_providers.replicate.base_url' => 'https://api.replicate.com/v1',
            'numen.image_providers.replicate.default_model' => 'black-forest-labs/flux-2-max',
        ]);

        $fakeImageBytes = 'FAKEIMG';

        Http::fake([
            'api.replicate.com/v1/predictions' => Http::response([
                'id' => 'pred_str',
                'status' => 'starting',
            ], 201),
            // Some Replicate models return a plain string, not an array
            'api.replicate.com/v1/predictions/pred_str' => Http::response([
                'id' => 'pred_str',
                'status' => 'succeeded',
                'output' => 'https://cdn.replicate.com/output/single.webp',
            ], 200),
            'cdn.replicate.com/output/single.webp' => Http::response($fakeImageBytes, 200, [
                'Content-Type' => 'image/webp',
            ]),
        ]);

        $provider = app(ReplicateImageProvider::class);
        $result = $provider->generate('A test', '1024x1024', 'vivid', 'standard');

        $this->assertEquals('image/webp', $result->mimeType);
        $this->assertEquals($fakeImageBytes, $result->imageData);
    }

    public function test_throws_on_prediction_creation_error(): void
    {
        config([
            'numen.image_providers.replicate.api_key' => 'r8_test_key',
            'numen.image_providers.replicate.base_url' => 'https://api.replicate.com/v1',
            'numen.image_providers.replicate.default_model' => 'black-forest-labs/flux-2-max',
        ]);

        Http::fake([
            'api.replicate.com/v1/predictions' => Http::response([
                'detail' => 'Model not found',
            ], 404),
        ]);

        $provider = app(ReplicateImageProvider::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Replicate prediction creation failed');

        $provider->generate('test', '1024x1024', 'vivid', 'standard');
    }
}
