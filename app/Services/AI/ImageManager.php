<?php

namespace App\Services\AI;

use App\Models\MediaAsset;
use App\Services\AI\ImageProviders\ImageProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Multi-provider image generation factory.
 *
 * Provider resolution order:
 *   1. persona model_config.generator_provider + model_config.generator_model
 *   2. config('numen.default_image_provider')
 *   3. First available provider from the full registry
 *
 * After generation, downloads the raw bytes, saves them to the 'public' disk,
 * and creates a MediaAsset record — identical to the old ImageGenerator contract.
 */
class ImageManager
{
    /** @var array<string, ImageProviderInterface> */
    private array $providers;

    public function __construct(
        ImageProviderInterface $openai,
        ImageProviderInterface $together,
        ImageProviderInterface $fal,
        ImageProviderInterface $replicate,
        private readonly CostTracker $costTracker,
    ) {
        $this->providers = [
            'openai' => $openai,
            'together' => $together,
            'fal' => $fal,
            'replicate' => $replicate,
        ];
    }

    /**
     * Generate an image, persist it to storage, and return a MediaAsset.
     *
     * @param  string  $prompt  Image generation prompt (already built by ImagePromptBuilder)
     * @param  string  $spaceId  Owning space ULID
     * @param  array  $personaConfig  Persona model_config array (may be empty)
     * @param  string  $size  Image dimensions, e.g. "1792x1024"
     * @param  string  $style  Style hint, e.g. "vivid" (passed to provider)
     * @param  string  $quality  Quality setting, e.g. "standard" or "hd"
     */
    public function generate(
        string $prompt,
        string $spaceId,
        array $personaConfig = [],
        string $size = '1792x1024',
        string $style = 'vivid',
        string $quality = 'standard',
    ): MediaAsset {
        $provider = $this->resolveProvider($personaConfig);
        $providerName = $provider->name();

        if (! $provider->isAvailable()) {
            throw new \RuntimeException(
                "Image provider '{$providerName}' is not available — check its API key in .env"
            );
        }

        Log::info('ImageManager: generating image', [
            'provider' => $providerName,
            'size' => $size,
            'style' => $style,
            'quality' => $quality,
            'prompt_preview' => Str::limit($prompt, 100),
        ]);

        $result = $provider->generate($prompt, $size, $style, $quality);

        // Persist to storage
        $ulid = Str::ulid();
        $extension = $this->extensionForMime($result->mimeType);
        $relativePath = "media/{$spaceId}/{$ulid}.{$extension}";
        $diskName = (string) config('numen.storage_disk', 'public');
        $disk = Storage::disk($diskName);
        $disk->makeDirectory("media/{$spaceId}");
        $disk->put($relativePath, $result->imageData);
        $sizeBytes = $disk->size($relativePath);

        // Track cost
        $this->costTracker->recordUsage($result->costUsd, $spaceId);

        // Create MediaAsset record
        $asset = MediaAsset::create([
            'space_id' => $spaceId,
            'filename' => "{$ulid}.{$extension}",
            'disk' => $diskName,
            'path' => $relativePath,
            'mime_type' => $result->mimeType,
            'size_bytes' => $sizeBytes,
            'source' => 'ai_generated',
            'ai_metadata' => [
                'prompt' => $prompt,
                'revised_prompt' => $result->revisedPrompt,
                'model' => $result->model,
                'provider' => $result->provider,
                'size' => $size,
                'style' => $style,
                'quality' => $quality,
                'cost_usd' => $result->costUsd,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);

        Log::info('ImageManager: image saved', [
            'asset_id' => $asset->id,
            'path' => $relativePath,
            'provider' => $providerName,
            'size_bytes' => $sizeBytes,
            'cost_usd' => $result->costUsd,
        ]);

        return $asset;
    }

    /**
     * Check whether any configured image provider is available.
     * Used by GenerateImage job to decide whether to skip gracefully.
     */
    public function hasAvailableProvider(): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve which provider to use for this request.
     *
     * Priority:
     *   1. persona model_config.generator_provider (if present and available)
     *   2. config('numen.default_image_provider')
     *   3. First available provider in the registry
     */
    private function resolveProvider(array $personaConfig): ImageProviderInterface
    {
        // 1. Explicit persona config
        $personaProviderName = $personaConfig['generator_provider'] ?? null;
        if ($personaProviderName && isset($this->providers[$personaProviderName])) {
            $provider = $this->providers[$personaProviderName];
            if ($provider->isAvailable()) {
                return $provider;
            }
            Log::warning('ImageManager: persona-specified provider unavailable, falling back', [
                'requested' => $personaProviderName,
            ]);
        }

        // 2. Config default
        $defaultName = (string) config('numen.default_image_provider', 'openai');
        if (isset($this->providers[$defaultName])) {
            $provider = $this->providers[$defaultName];
            if ($provider->isAvailable()) {
                return $provider;
            }
        }

        // 3. First available
        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                return $provider;
            }
        }

        throw new \RuntimeException(
            'No image provider is available. Configure at least one of: OPENAI_API_KEY, TOGETHER_API_KEY, FAL_API_KEY, REPLICATE_API_KEY'
        );
    }

    /**
     * Map a MIME type to a file extension for storage.
     */
    private function extensionForMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/webp' => 'webp',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            default => 'png',
        };
    }
}
