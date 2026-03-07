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

        // Normalize size based on model support
        $normalizedSize = $this->normalizeSizeForModel($model, $size);

        Log::info('OpenAIImageProvider: generating image', [
            'model' => $model,
            'requested_size' => $size,
            'normalized_size' => $normalizedSize,
            'style' => $style,
            'quality' => $quality,
            'prompt_preview' => Str::limit($prompt, 100),
        ]);

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $normalizedSize,
        ];

        // Quality/style support varies by model
        // Check more specific models first (gpt-image-1.5 contains gpt-image-1 as substring)
        if (str_starts_with($model, 'gpt-image-1.5')) {
            // gpt-image-1.5 supports quality and style directly
            $payload['quality'] = $quality;
            $payload['style'] = $style;
        } elseif (str_starts_with($model, 'gpt-image-1')) {
            // gpt-image-1 only supports: 'low', 'medium', 'high', 'auto'
            // Map standard Numen quality values to gpt-image-1 accepted values
            $gptImageQuality = match ($quality) {
                'hd' => 'high',
                default => 'medium', // 'standard' → 'medium'
            };
            $payload['quality'] = $gptImageQuality;
            // gpt-image-1 returns base64 by default; request it explicitly
            $payload['output_format'] = 'png';
        } elseif (str_starts_with($model, 'dall-e')) {
            // dall-e supports quality and style directly
            $payload['quality'] = $quality;
            $payload['style'] = $style;
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
        // Prefer the image-specific key; fall back to the shared LLM OpenAI key.
        // This ensures that a key configured via the Admin Settings UI
        // (stored under numen.providers.openai.api_key) also enables image generation.
        return (string) (
            config('numen.image_providers.openai.api_key')
            ?: config('numen.providers.openai.api_key', '')
        );
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('numen.image_providers.openai.base_url', 'https://api.openai.com/v1'), '/');
    }

    private function defaultModel(): string
    {
        return (string) config('numen.image_providers.openai.default_model', 'gpt-image-1');
    }

    /**
     * Normalize requested size to what the model supports.
     *
     * @param  string  $model  Model name (gpt-image-1, gpt-image-1.5, dall-e-3, etc.)
     * @param  string  $requestedSize  Requested size (e.g., '1792x1024', '1024x1024')
     * @return string Normalized size supported by the model
     */
    private function normalizeSizeForModel(string $model, string $requestedSize): string
    {
        // gpt-image-1 supports: 1024x1024, 1024x1536, 1536x1024, auto
        if (str_starts_with($model, 'gpt-image-1') && ! str_starts_with($model, 'gpt-image-1.5')) {
            $supported = ['1024x1024', '1024x1536', '1536x1024', 'auto'];
            if (in_array($requestedSize, $supported)) {
                return $requestedSize;
            }

            // Map unsupported sizes to closest match
            return match ($requestedSize) {
                '1792x1024' => '1536x1024',  // dall-e default → closest gpt-image-1 size
                '1024x1792' => '1024x1536',  // dall-e widescreen → closest gpt-image-1 size
                default => '1024x1024',       // fallback to square
            };
        }

        // gpt-image-1.5 and dall-e-3 both support: 1024x1024, 1792x1024, 1024x1792, auto
        if (str_starts_with($model, 'gpt-image-1.5') || str_starts_with($model, 'dall-e')) {
            $supported = ['1024x1024', '1792x1024', '1024x1792', 'auto'];
            if (in_array($requestedSize, $supported)) {
                return $requestedSize;
            }

            // Map unsupported sizes to closest match
            return match ($requestedSize) {
                '1536x1024' => '1792x1024',  // gpt-image-1 → dall-e equivalent
                '1024x1536' => '1024x1792',  // gpt-image-1 → dall-e equivalent
                default => '1024x1024',       // fallback to square
            };
        }

        // Unknown model — return as-is and let OpenAI error handle it
        return $requestedSize;
    }
}
