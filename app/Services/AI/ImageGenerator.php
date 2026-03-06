<?php

namespace App\Services\AI;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates images via OpenAI DALL-E 3 API.
 * Downloads the temporary URL immediately and saves to local storage.
 *
 * IMPORTANT: Uses lazy config reading — no constructor caching of API keys.
 */
class ImageGenerator
{
    /** Cost per image by size (USD) */
    private const COST_MAP = [
        '1024x1024' => 0.04,
        '1792x1024' => 0.08,
        '1024x1792' => 0.12,
    ];

    /**
     * Generate an image and persist it as a MediaAsset.
     */
    public function generate(
        string $prompt,
        string $spaceId,
        string $size = '1792x1024',
        string $style = 'vivid',
        string $quality = 'standard',
    ): MediaAsset {
        $apiKey = $this->apiKey();

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key not configured. Set OPENAI_API_KEY in .env');
        }

        Log::info('ImageGenerator: requesting DALL-E 3 image', [
            'size' => $size,
            'style' => $style,
            'prompt_preview' => Str::limit($prompt, 100),
        ]);

        // Call OpenAI Images API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(120)
            ->post($this->baseUrl() . '/images/generations', [
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => $size,
                'quality' => $quality,
                'style' => $style,
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message', $response->body());
            Log::error('ImageGenerator: DALL-E 3 API error', [
                'status' => $response->status(),
                'error' => $error,
            ]);
            throw new \RuntimeException("DALL-E 3 API error: {$error}");
        }

        $data = $response->json();
        $imageUrl = $data['data'][0]['url'] ?? null;
        $revisedPrompt = $data['data'][0]['revised_prompt'] ?? $prompt;

        if (empty($imageUrl)) {
            throw new \RuntimeException('DALL-E 3 returned no image URL');
        }

        // Download the image immediately (OpenAI hosts for ~1 hour only)
        $imageData = Http::timeout(60)->get($imageUrl);

        if ($imageData->failed()) {
            throw new \RuntimeException('Failed to download generated image from OpenAI');
        }

        // Save to storage (explicitly use 'public' disk so files land in storage/app/public/)
        $ulid = Str::ulid();
        $relativePath = "media/{$spaceId}/{$ulid}.webp";
        $disk = Storage::disk('public');

        // Ensure directory exists
        $disk->makeDirectory("media/{$spaceId}");

        // Save the image (PNG from DALL-E, stored as-is with .webp extension for future conversion)
        $disk->put($relativePath, $imageData->body());

        $sizeBytes = $disk->size($relativePath);

        // Track cost
        $costUsd = self::COST_MAP[$size] ?? 0.08;
        $costTracker = app(CostTracker::class);
        $costTracker->recordUsage($costUsd, $spaceId);

        // Create MediaAsset record
        $asset = MediaAsset::create([
            'space_id' => $spaceId,
            'filename' => "{$ulid}.webp",
            'disk' => 'public',
            'path' => $relativePath,
            'mime_type' => 'image/png', // DALL-E returns PNG
            'size_bytes' => $sizeBytes,
            'source' => 'ai_generated',
            'ai_metadata' => [
                'prompt' => $prompt,
                'revised_prompt' => $revisedPrompt,
                'model' => 'dall-e-3',
                'size' => $size,
                'style' => $style,
                'quality' => $quality,
                'cost_usd' => $costUsd,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);

        Log::info('ImageGenerator: image saved', [
            'asset_id' => $asset->id,
            'path' => $relativePath,
            'size_bytes' => $sizeBytes,
            'cost_usd' => $costUsd,
        ]);

        return $asset;
    }

    /** Lazy config read — never cache in constructor */
    private function apiKey(): string
    {
        return (string) config('numen.providers.openai.api_key', '');
    }

    /** Lazy config read */
    private function baseUrl(): string
    {
        return rtrim((string) config('numen.providers.openai.base_url', 'https://api.openai.com/v1'), '/');
    }
}
