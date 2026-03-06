<?php

namespace App\Services\AI\ImageProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OpenAI image generation provider.
 * Supports: gpt-image-1, gpt-image-1.5, dall-e-3
 *
 * gpt-image-1 returns base64-encoded PNG in the response body.
 * dall-e-3 returns a temporary URL that must be downloaded immediately.
 */
class OpenAIImageProvider implements ImageProviderInterface
{
    public function name(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey());
    }

    public function generate(string $prompt, string $size, string $style, string $quality): ImageResult
    {
        $apiKey = $this->apiKey();

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI image API key not configured. Set OPENAI_API_KEY in .env');
        }

        $model = $this->defaultModel();

        Log::info('OpenAIImageProvider: generating image', [
            'model' => $model,
            'size' => $size,
            'style' => $style,
            'quality' => $quality,
            'prompt_preview' => Str::limit($prompt, 100),
        ]);

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
        ];

        // gpt-image-1 does not support style/quality in the same way as dall-e-3
        if (str_starts_with($model, 'dall-e') || str_starts_with($model, 'gpt-image-1.5')) {
            $payload['quality'] = $quality;
            $payload['style'] = $style;
        } elseif (str_starts_with($model, 'gpt-image-1')) {
            $payload['quality'] = $quality;
            // gpt-image-1 returns base64 by default; request it explicitly
            $payload['output_format'] = 'png';
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(180)
            ->post($this->baseUrl().'/images/generations', $payload);

        if ($response->failed()) {
            $error = $response->json('error.message', $response->body());
            Log::error('OpenAIImageProvider: API error', [
                'status' => $response->status(),
                'error' => $error,
                'model' => $model,
            ]);
            throw new \RuntimeException("OpenAI image API error ({$model}): {$error}");
        }

        $data = $response->json();
        $item = $data['data'][0] ?? null;

        if (! $item) {
            throw new \RuntimeException("OpenAI image API returned no data for model {$model}");
        }

        $revisedPrompt = $item['revised_prompt'] ?? null;

        // gpt-image-1 returns base64; dall-e-3 returns a URL
        if (! empty($item['b64_json'])) {
            $imageData = base64_decode($item['b64_json']);
            $mimeType = 'image/png';
        } elseif (! empty($item['url'])) {
            $imageResponse = Http::timeout(60)->get($item['url']);
            if ($imageResponse->failed()) {
                throw new \RuntimeException('Failed to download generated image from OpenAI CDN');
            }
            $imageData = $imageResponse->body();
            $mimeType = 'image/png';
        } else {
            throw new \RuntimeException("OpenAI image API returned neither b64_json nor url for model {$model}");
        }

        return new ImageResult(
            imageData: $imageData,
            mimeType: $mimeType,
            model: $model,
            provider: 'openai',
            revisedPrompt: $revisedPrompt,
            costUsd: $this->estimateCost($model, $size, $quality),
        );
    }

    private function estimateCost(string $model, string $size, string $quality): float
    {
        // dall-e-3 pricing (approximate)
        if (str_starts_with($model, 'dall-e-3')) {
            return match (true) {
                $size === '1024x1024' && $quality === 'standard' => 0.04,
                $size === '1024x1024' && $quality === 'hd' => 0.08,
                in_array($size, ['1792x1024', '1024x1792']) && $quality === 'standard' => 0.08,
                in_array($size, ['1792x1024', '1024x1792']) && $quality === 'hd' => 0.12,
                default => 0.08,
            };
        }

        // gpt-image-1 pricing (approximate)
        return match (true) {
            $quality === 'hd' => 0.19,
            $quality === 'medium' => 0.07,
            default => 0.04, // standard / low
        };
    }

    private function apiKey(): string
    {
        return (string) config('numen.image_providers.openai.api_key', '');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('numen.image_providers.openai.base_url', 'https://api.openai.com/v1'), '/');
    }

    private function defaultModel(): string
    {
        return (string) config('numen.image_providers.openai.default_model', 'gpt-image-1');
    }
}
