<?php

namespace App\Services\AI\ImageProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Replicate (Cloudflare) image generation provider.
 *
 * Replicate hosts all major image models under one API with async polling:
 *   1. POST /v1/predictions → creates a prediction, returns { id, status: "starting" }
 *   2. GET  /v1/predictions/{id} → poll until status is "succeeded" or "failed"
 *   3. Download output URL from the completed prediction
 *
 * Supported models (examples):
 *   - black-forest-labs/flux-2-max
 *   - openai/gpt-image-1.5
 *   - google/nano-banana-pro
 *   - stability-ai/stable-diffusion-3
 *
 * Auth: Bearer token via REPLICATE_API_KEY
 */
class ReplicateImageProvider implements ImageProviderInterface
{
    /** Maximum seconds to wait for prediction to complete */
    private const POLL_TIMEOUT_SECONDS = 300;

    /** Seconds between poll attempts */
    private const POLL_INTERVAL_SECONDS = 2;

    private ?string $modelOverride = null;

    public function name(): string
    {
        return 'replicate';
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
            throw new \RuntimeException('Replicate API key not configured. Set REPLICATE_API_KEY in .env');
        }

        $model = $this->defaultModel();

        Log::info('ReplicateImageProvider: creating prediction', [
            'model' => $model,
            'size' => $size,
            'prompt_preview' => Str::limit($prompt, 100),
        ]);

        [$width, $height] = $this->parseSize($size);

        // Step 1: Create prediction
        $prediction = $this->createPrediction($apiKey, $model, $prompt, $width, $height, $quality);
        $predictionId = $prediction['id'] ?? null;

        if (empty($predictionId)) {
            throw new \RuntimeException("Replicate did not return a prediction ID for model {$model}");
        }

        Log::info('ReplicateImageProvider: prediction created, polling', [
            'prediction_id' => $predictionId,
            'model' => $model,
        ]);

        // Step 2: Poll until complete
        $completed = $this->pollUntilComplete($apiKey, $predictionId);

        if ($completed['status'] !== 'succeeded') {
            $error = $completed['error'] ?? 'Unknown error';
            Log::error('ReplicateImageProvider: prediction failed', [
                'prediction_id' => $predictionId,
                'status' => $completed['status'],
                'error' => $error,
            ]);
            throw new \RuntimeException("Replicate prediction failed ({$model}): {$error}");
        }

        // Step 3: Extract output URL — Replicate output can be a string or array
        $output = $completed['output'] ?? null;
        $imageUrl = is_array($output) ? ($output[0] ?? null) : $output;

        if (empty($imageUrl)) {
            throw new \RuntimeException("Replicate prediction succeeded but returned no output URL ({$model})");
        }

        Log::info('ReplicateImageProvider: prediction succeeded, downloading', [
            'prediction_id' => $predictionId,
            'url_preview' => Str::limit($imageUrl, 80),
        ]);

        // Step 4: Download the image
        $download = Http::withHeaders(['Authorization' => 'Bearer '.$apiKey])
            ->timeout(60)
            ->get($imageUrl);

        if ($download->failed()) {
            throw new \RuntimeException("Failed to download Replicate output image from {$imageUrl}");
        }

        $mimeType = $this->detectMimeType($imageUrl, $download->header('Content-Type'));

        return new ImageResult(
            imageData: $download->body(),
            mimeType: $mimeType,
            model: $model,
            provider: 'replicate',
            revisedPrompt: null,
            costUsd: $this->estimateCost($model),
        );
    }

    /**
     * POST /v1/predictions to create a new async prediction.
     *
     * @return array<string, mixed>
     */
    private function createPrediction(
        string $apiKey,
        string $model,
        string $prompt,
        int $width,
        int $height,
        string $quality,
    ): array {
        // Replicate model refs can include a version hash: "owner/model:version"
        // If no version hash provided, use the latest version endpoint
        $payload = $this->buildPredictionPayload($model, $prompt, $width, $height, $quality);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
            'Prefer' => 'wait', // ask Replicate to wait up to 60s before returning
        ])
            ->timeout(90)
            ->post($this->baseUrl().'/predictions', $payload);

        if ($response->failed()) {
            $error = $response->json('detail', $response->body());
            throw new \RuntimeException("Replicate prediction creation failed ({$model}): {$error}");
        }

        return $response->json();
    }

    /**
     * Build the prediction request payload.
     * Replicate accepts either a versioned model ref or a latest-model ref.
     *
     * @return array<string, mixed>
     */
    private function buildPredictionPayload(
        string $model,
        string $prompt,
        int $width,
        int $height,
        string $quality,
    ): array {
        $input = [
            'prompt' => $prompt,
            'width' => $width,
            'height' => $height,
            'num_outputs' => 1,
            'output_format' => 'png',
            'num_inference_steps' => $quality === 'hd' ? 28 : 4,
        ];

        // If model includes a version hash ("owner/model:sha256hash"), use versioned endpoint
        if (str_contains($model, ':')) {
            [$modelRef, $version] = explode(':', $model, 2);

            return [
                'version' => $version,
                'input' => $input,
            ];
        }

        // Otherwise use the "model" key (Replicate's latest-version shorthand)
        return [
            'model' => $model,
            'input' => $input,
        ];
    }

    /**
     * Poll the prediction endpoint until status is "succeeded" or "failed".
     * Respects POLL_TIMEOUT_SECONDS hard limit.
     *
     * @return array<string, mixed>
     */
    private function pollUntilComplete(string $apiKey, string $predictionId): array
    {
        $deadline = time() + self::POLL_TIMEOUT_SECONDS;
        $headers = ['Authorization' => 'Bearer '.$apiKey];

        while (time() < $deadline) {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->get($this->baseUrl().'/predictions/'.$predictionId);

            if ($response->failed()) {
                throw new \RuntimeException("Replicate poll failed for prediction {$predictionId}: ".$response->body());
            }

            $prediction = $response->json();
            $status = $prediction['status'] ?? 'unknown';

            if (in_array($status, ['succeeded', 'failed', 'canceled'], strict: true)) {
                return $prediction;
            }

            Log::debug('ReplicateImageProvider: polling', [
                'prediction_id' => $predictionId,
                'status' => $status,
            ]);

            sleep(self::POLL_INTERVAL_SECONDS);
        }

        throw new \RuntimeException(
            "Replicate prediction {$predictionId} timed out after ".self::POLL_TIMEOUT_SECONDS.'s'
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
        // Replicate charges per-second of GPU time; these are rough per-image estimates
        return match (true) {
            str_contains($model, 'flux-2-max') => 0.05,
            str_contains($model, 'flux-pro') => 0.055,
            str_contains($model, 'flux-dev') => 0.025,
            str_contains($model, 'flux-schnell'), str_contains($model, 'flux-2-schnell') => 0.003,
            str_contains($model, 'gpt-image') => 0.08,
            default => 0.02,
        };
    }

    private function apiKey(): string
    {
        return (string) config('numen.image_providers.replicate.api_key', '');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('numen.image_providers.replicate.base_url', 'https://api.replicate.com/v1'), '/');
    }

    private function defaultModel(): string
    {
        if ($this->modelOverride) {
            return $this->modelOverride;
        }

        return (string) config('numen.image_providers.replicate.default_model', 'black-forest-labs/flux-2-max');
    }
}
