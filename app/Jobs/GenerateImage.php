<?php

namespace App\Jobs;

use App\Models\AIGenerationLog;
use App\Models\Persona;
use App\Models\PipelineRun;
use App\Pipelines\PipelineExecutor;
use App\Services\AI\ImageManager;
use App\Services\AI\ImagePromptBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300; // Replicate/fal can take a while for large models

    public int $maxExceptions = 2;

    public function __construct(
        public PipelineRun $run,
        public array $stage,
    ) {
        $this->onQueue('ai-pipeline');
    }

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(
        ImagePromptBuilder $promptBuilder,
        ImageManager $imageManager,
        PipelineExecutor $executor,
    ): void {
        Log::info('GenerateImage: starting', [
            'run_id' => $this->run->id,
            'stage' => $this->stage['name'],
        ]);

        // Gracefully skip if no image provider is available
        if (! $imageManager->hasAvailableProvider()) {
            Log::warning('GenerateImage: no image provider configured — skipping ai_illustrate stage', [
                'run_id' => $this->run->id,
                'stage' => $this->stage['name'],
            ]);
            $executor->advance($this->run, [
                'stage' => $this->stage['name'],
                'success' => true,
                'skipped' => true,
                'summary' => 'Skipped: no image provider API key configured (OPENAI_API_KEY, TOGETHER_API_KEY, FAL_API_KEY, or REPLICATE_API_KEY)',
            ]);

            return;
        }

        try {
            $content = $this->run->content;

            if (! $content) {
                Log::warning('GenerateImage: no content attached to pipeline run', [
                    'run_id' => $this->run->id,
                ]);
                $this->run->markFailed('No content found for image generation');

                return;
            }

            $version = $content->currentVersion;
            $title = $version->title ?? 'Untitled';
            $excerpt = $version->excerpt ?? '';
            $tags = $content->taxonomy['tags'] ?? [];
            $contentType = $content->contentType->slug ?? 'blog_post';
            $spaceId = $content->space_id;

            // Resolve persona config — prefer stage agent_role, fall back to space's illustrator persona
            $personaConfig = $this->resolvePersonaConfig($spaceId);

            // Stage config overrides (size/style/quality can be set per-stage or come from persona)
            $size = $this->stage['config']['size'] ?? $personaConfig['size'] ?? '1792x1024';
            $style = $this->stage['config']['style'] ?? $personaConfig['style'] ?? 'vivid';
            $quality = $this->stage['config']['quality'] ?? $personaConfig['quality'] ?? 'standard';

            // Step 1: Build an optimized prompt using persona's prompt LLM
            $promptStart = microtime(true);
            $prompt = $promptBuilder->build($title, $excerpt, $tags, $contentType, $personaConfig);
            $promptLatency = (int) ((microtime(true) - $promptStart) * 1000);

            Log::info('GenerateImage: prompt built', [
                'prompt_preview' => Str::limit($prompt, 120),
                'prompt_latency_ms' => $promptLatency,
            ]);

            // Step 2: Generate and save the image via the resolved provider
            $imageStart = microtime(true);
            $asset = $imageManager->generate($prompt, $spaceId, $personaConfig, $size, $style, $quality);
            $imageLatency = (int) ((microtime(true) - $imageStart) * 1000);

            // Step 3: Attach to content as hero image
            $content->update(['hero_image_id' => $asset->id]);

            // Step 4: Log the image generation
            $costUsd = $asset->ai_metadata['cost_usd'] ?? 0.0;
            $usedModel = $asset->ai_metadata['model'] ?? 'unknown';
            $usedProvider = $asset->ai_metadata['provider'] ?? 'unknown';

            AIGenerationLog::create([
                'pipeline_run_id' => $this->run->id,
                'model' => $usedModel,
                'purpose' => 'image_generation',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response' => json_encode([
                    'asset_id' => $asset->id,
                    'provider' => $usedProvider,
                    'size' => $size,
                    'style' => $style,
                    'revised_prompt' => $asset->ai_metadata['revised_prompt'] ?? null,
                ]),
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost_usd' => $costUsd,
                'latency_ms' => $imageLatency,
                'stop_reason' => 'complete',
                'metadata' => [
                    'type' => 'image',
                    'model' => $usedModel,
                    'provider' => $usedProvider,
                    'size' => $size,
                    'style' => $style,
                    'prompt_latency_ms' => $promptLatency,
                    'asset_path' => $asset->path,
                ],
            ]);

            Log::info('GenerateImage: hero image attached', [
                'content_id' => $content->id,
                'asset_id' => $asset->id,
                'provider' => $usedProvider,
                'model' => $usedModel,
            ]);

            $executor->advance($this->run, [
                'stage' => $this->stage['name'],
                'success' => true,
                'asset_id' => $asset->id,
                'provider' => $usedProvider,
                'model' => $usedModel,
                'prompt' => $prompt,
                'summary' => "Generated hero image ({$size}) for \"{$title}\" via {$usedProvider}/{$usedModel}",
            ]);

        } catch (\Throwable $e) {
            Log::error('GenerateImage: failed', [
                'run_id' => $this->run->id,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->run->markFailed("Image generation failed after {$this->tries} attempts: {$e->getMessage()}");
            }

            throw $e;
        }
    }

    /**
     * Load the illustrator persona's model_config for this space.
     * Returns an empty array if no persona is found — ImageManager and
     * ImagePromptBuilder both have sensible defaults.
     *
     * @return array<string, mixed>
     */
    private function resolvePersonaConfig(string $spaceId): array
    {
        // Prefer agent_role from stage config (explicit persona slug)
        $agentRole = $this->stage['agent_role'] ?? 'illustrator';

        $persona = Persona::where('space_id', $spaceId)
            ->where('role', $agentRole)
            ->where('is_active', true)
            ->first();

        if (! $persona) {
            Log::debug('GenerateImage: no persona found for role, using defaults', [
                'role' => $agentRole,
                'space_id' => $spaceId,
            ]);

            return [];
        }

        return $persona->model_config ?? [];
    }
}
