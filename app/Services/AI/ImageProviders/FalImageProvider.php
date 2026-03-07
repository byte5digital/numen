<?php

namespace App\Services\AI\ImageProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * fal.ai image generation provider.
 * Uses the fal.run REST API with model-specific endpoints.
 *
 * Endpoint pattern: POST https://fal.run/{model}
 * fal.ai returns a JSON response with an `images` array containing URLs.
 * The images are immediately available (synchronous for most models).
 *
 * Supported models: fal-ai/flux/schnell, fal-ai/flux/dev, fal-ai/flux-pro, etc.
 */
class FalImageProvider implements ImageProviderInterface
{
    private ?string $modelOverride = null;

    public function name(): string
    {
        return 'fal';
    }

    public function setModel(string $model): self
    {
        $this->modelOverride = $model;

        return $this;
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey());
    }

    public function generate(string $prompt, string $size, string $style, string $quality): ImageResult
    {
        $apiKey = $this->apiKey();

        if (empty($apiKey)) {
            throw new \RuntimeException('fal.ai API key not configured. Set FAL_API_KEY in .env');
        }

        $model = $this->defaultModel();

        Log::info('FalImageProvider: generating image', [
            'model' => $model,
            'size' => $size,
            'prompt_preview' => Str::limit($prompt, 100),
        ]);

        [$width, $height] = $this->parseSize($size);

        // fal.ai endpoint is base_url/{model}
        $endpoint = rtrim($this->baseUrl(), '/').'/'.$model;

        $response = Http::withHeaders([
            'Authorization' => 'Key '.$apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(120)
            ->post($endpoint, [
                'prompt' => $prompt,
                'image_size' => [
                    'width' => $width,
                    'height' => $height,
                ],
                'num_images' => 1,
                'enable_safety_checker' => true,
                'num_inference_steps' => $quality === 'hd' ? 28 : 4,
            ]);

        if ($response->failed()) {
            $error = $response->json('detail', $response->body());
            Log::error('FalImageProvider: API error', [
                'status' => $response->status(),
                'error' => $error,
                'model' => $model,
            ]);
            throw new \RuntimeException("fal.ai image error ({$model}): {$error}");
        }

        $data = $response->json();
        $imageUrl = $data['images'][0]['url'] ?? null;

        if (empty($imageUrl)) {
            throw new \RuntimeException("fal.ai returned no image URL for model {$model}");
        }

        // Download the image from fal CDN
        $download = Http::timeout(60)->get($imageUrl);
        if ($download->failed()) {
            throw new \RuntimeException('Failed to download generated image from fal.ai CDN');
        }

        $mimeType = $this->detectMimeType($imageUrl, $download->header('Content-Type'));

        return new ImageResult(
            imageData: $download->body(),
            mimeType: $mimeType,
            model: $model,
            provider: 'fal',
            revisedPrompt: null,
            costUsd: $this->estimateCost($model),
        );
    }

    /**
     * Parse "1792x1024" → [1792, 1024].
     *
     * @return array{0: int, 1: int}
     */
    private function parseSize(string $size): array
    {
        $parts = explode('x', $size);

        return [(int) ($parts[0] ?? 1024), (int) ($parts[1] ?? 1024)];
    }

    private function detectMimeType(string $url, ?string $contentType): string
    {
        if ($contentType && str_contains($contentType, 'image/')) {
            return explode(';', $contentType)[0];
        }

        return match (true) {
            str_ends_with($url, '.webp') => 'image/webp',
            str_ends_with($url, '.jpg'), str_ends_with($url, '.jpeg') => 'image/jpeg',
            default => 'image/png',
        };
    }

    private function estimateCost(string $model): float
    {
        return match (true) {
            str_contains($model, 'schnell') => 0.003,
            str_contains($model, 'dev') => 0.025,
            str_contains($model, 'pro') => 0.055,
            default => 0.01,
        };
    }

    private function apiKey(): string
    {
        return (string) config('numen.image_providers.fal.api_key', '');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('numen.image_providers.fal.base_url', 'https://fal.run'), '/');
    }

    private function defaultModel(): string
    {
        if ($this->modelOverride) {
            return $this->modelOverride;
        }

        return (string) config('numen.image_providers.fal.default_model', 'fal-ai/flux/schnell');
    }
}
