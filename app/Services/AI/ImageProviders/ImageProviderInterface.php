<?php

namespace App\Services\AI\ImageProviders;

interface ImageProviderInterface
{
    /**
     * Generate an image from a text prompt.
     *
     * @param  string  $prompt  The image generation prompt
     * @param  string  $size  Image dimensions (e.g. "1792x1024")
     * @param  string  $style  Style hint (e.g. "vivid", "natural") — provider-dependent
     * @param  string  $quality  Quality setting (e.g. "standard", "hd") — provider-dependent
     * @return ImageResult Raw result with image bytes and metadata
     */
    public function generate(string $prompt, string $size, string $style, string $quality): ImageResult;

    /**
     * Set the model to use for the next generate() call.
     * If not called, the provider's default model is used.
     *
     * @param  string  $model  Model identifier (provider-specific)
     * @return self For method chaining
     */
    public function setModel(string $model): self;

    /**
     * Whether this provider is available (API key present, not rate-limited).
     */
    public function isAvailable(): bool;

    /**
     * Human-readable provider name.
     */
    public function name(): string;
}
