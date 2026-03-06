<?php

namespace App\Services\AI\ImageProviders;

/**
 * Value object representing the result of an image generation call.
 * Contains raw image bytes — saving to storage is handled by ImageManager.
 */
final class ImageResult
{
    public function __construct(
        /** Raw binary image bytes */
        public readonly string $imageData,
        /** MIME type of the image (e.g. "image/png", "image/webp") */
        public readonly string $mimeType,
        /** Model used for generation (e.g. "dall-e-3", "gpt-image-1") */
        public readonly string $model,
        /** Provider name (e.g. "openai", "together", "fal") */
        public readonly string $provider,
        /** Provider-revised prompt (if any) */
        public readonly ?string $revisedPrompt = null,
        /** Estimated cost in USD */
        public readonly float $costUsd = 0.0,
    ) {}
}
