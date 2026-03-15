<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Services\AI\LLMManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaTaggingService
{
    public function __construct(
        private readonly LLMManager $llmManager,
    ) {}

    /**
     * Whether AI auto-tagging is enabled for this installation.
     * Opt-in via config('media.ai_tagging') or MEDIA_AI_TAGGING env var.
     */
    public function isEnabled(): bool
    {
        return (bool) config('media.ai_tagging', false);
    }

    /**
     * Generate AI tags for a media asset using vision analysis.
     * Only processes image assets; returns [] for non-images or on failure.
     *
     * @return string[]
     */
    public function generateTags(MediaAsset $asset): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        if (! str_starts_with($asset->mime_type, 'image/')) {
            return [];
        }

        // SVG is not a raster image — skip vision analysis
        if ($asset->mime_type === 'image/svg+xml') {
            return [];
        }

        try {
            $imageData = $this->fetchImageBase64($asset);
            if ($imageData === null) {
                return [];
            }

            $response = $this->llmManager->complete([
                'model' => 'claude-haiku-4-5-20251001',
                'system' => 'You are an image analysis assistant. Return only valid JSON arrays of descriptive tags.',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $asset->mime_type,
                                    'data' => $imageData,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Analyze this image and return 5-10 descriptive tags as a JSON array of strings. '
                                    .'Focus on subject matter, colors, mood, style, and setting. '
                                    .'Example: ["landscape", "sunset", "orange", "serene", "mountains"]. '
                                    .'Return only the JSON array, no other text.',
                            ],
                        ],
                    ],
                ],
                'max_tokens' => 256,
                'temperature' => 0.3,
                '_purpose' => 'media_auto_tagging',
            ]);

            return $this->parseTagsFromResponse($response->content);
        } catch (\Throwable $e) {
            Log::warning('MediaTaggingService: failed to generate tags', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Merge AI-generated tags with existing manual tags and persist.
     */
    public function applyTags(MediaAsset $asset, array $tags): MediaAsset
    {
        if (empty($tags)) {
            return $asset;
        }

        $existing = (array) ($asset->tags ?? []);
        $merged = array_values(array_unique(array_merge($existing, $tags)));

        $asset->tags = $merged;
        $asset->save();

        return $asset;
    }

    /**
     * Read the asset file and return base64-encoded content, or null on failure.
     */
    private function fetchImageBase64(MediaAsset $asset): ?string
    {
        try {
            $contents = Storage::disk($asset->disk)->get($asset->path);
            if ($contents === null || $contents === false) {
                return null;
            }

            return base64_encode($contents);
        } catch (\Throwable $e) {
            Log::debug('MediaTaggingService: could not read image for tagging', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parse a JSON array of tags from LLM response content.
     *
     * @return string[]
     */
    private function parseTagsFromResponse(string $content): array
    {
        $content = trim($content);

        // Strip markdown code fences if present
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        $content = trim($content);

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(fn ($tag) => is_string($tag) ? trim(strtolower($tag)) : null, $decoded),
                fn ($tag) => $tag !== null && $tag !== '',
            )
        );
    }
}
