<?php

namespace App\Services\AI\ImageProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Together AI image generation provider.
 * Exposes an OpenAI-compatible /images/generations endpoint.
 * Supports FLUX.1 family: black-forest-labs/FLUX.1-schnell, FLUX.1-dev, etc.
 *
 * Together returns a base64-encoded image in the response body.
 */
class TogetherImageProvider implements ImageProviderInterface
{
    private ?string $modelOverride = null;

    public function name(): string
    {
        return 'together';
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
            throw new \RuntimeException('Together AI API key not configured. Set TOGETHER_API_KEY in .env');
        }

        $model = $this->defaultModel();

        Log::info('TogetherImageProvider: generating image', [
            'model' => $model,
            'size' => $size,
            'prompt_preview' => Str::limit($prompt, 100),
        ]);

        [$width, $height] = $this->parseSize($size);

        // Together AI uses OpenAI-compatible endpoint with width/height instead of "size"
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(120)
            ->post($this->baseUrl().'/images/generations', [
                'model' => $model,
                'prompt' => $prompt,
                'n' => 1,
                'width' => $width,
                'height' => $height,
                'steps' => $quality === 'hd' ? 28 : 4, // schnell runs in 4 steps
                'response_format' => 'b64_json',
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message', $response->body());
            Log::error('TogetherImageProvider: API error', [
                'status' => $response->status(),
                'error' => $error,
                'model' => $model,
            ]);
            throw new \RuntimeException("Together AI image error ({$model}): {$error}");
        }

        $data = $response->json();
        $item = $data['data'][0] ?? null;

        if (! $item || empty($item['b64_json'])) {
            throw new \RuntimeException("Together AI returned no image data for model {$model}");
        }

        return new ImageResult(
            imageData: base64_decode($item['b64_json']),
            mimeType: 'image/png',
            model: $model,
            provider: 'together',
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

    private function estimateCost(string $model): float
    {
        // Together AI pricing per image (approximate, as of 2025)
        return match (true) {
            str_contains($model, 'schnell') => 0.003,
            str_contains($model, 'dev') => 0.025,
            str_contains($model, 'pro') => 0.055,
            default => 0.01,
        };
    }

    private function apiKey(): string
    {
        return (string) config('numen.image_providers.together.api_key', '');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('numen.image_providers.together.base_url', 'https://api.together.xyz/v1'), '/');
    }

    private function defaultModel(): string
    {
        if ($this->modelOverride) {
            return $this->modelOverride;
        }

        return (string) config('numen.image_providers.together.default_model', 'black-forest-labs/FLUX.1-schnell');
    }
}
