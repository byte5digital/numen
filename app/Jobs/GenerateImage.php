<?php

namespace App\Jobs;

use App\Models\AIGenerationLog;
use App\Models\PipelineRun;
use App\Pipelines\PipelineExecutor;
use App\Services\AI\ImageGenerator;
use App\Services\AI\ImagePromptBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

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

    public function handle(ImagePromptBuilder $promptBuilder, ImageGenerator $imageGenerator, PipelineExecutor $executor): void
    {
        Log::info('GenerateImage: starting', [
            'run_id' => $this->run->id,
            'stage' => $this->stage['name'],
        ]);

        // Gracefully skip if no OpenAI API key is configured
        if (empty(config('numen.providers.openai.api_key'))) {
            Log::warning('GenerateImage: OPENAI_API_KEY not configured — skipping ai_illustrate stage', [
                'run_id' => $this->run->id,
                'stage' => $this->stage['name'],
            ]);
            $executor->advance($this->run, [
                'stage' => $this->stage['name'],
                'success' => true,
                'skipped' => true,
                'summary' => 'Skipped: OPENAI_API_KEY not configured',
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
            $title = $version?->title ?? 'Untitled';
            $excerpt = $version?->excerpt ?? '';
            $tags = $content->taxonomy['tags'] ?? [];
            $contentType = $content->contentType?->slug ?? 'blog_post';
            $spaceId = $content->space_id;

            // Stage config
            $size = $this->stage['config']['size'] ?? '1792x1024';
            $style = $this->stage['config']['style'] ?? 'vivid';

            // Step 1: Build an optimized prompt
            $promptStart = microtime(true);
            $prompt = $promptBuilder->build($title, $excerpt, $tags, $contentType);
            $promptLatency = (int) ((microtime(true) - $promptStart) * 1000);

            Log::info('GenerateImage: prompt built', [
                'prompt_preview' => \Illuminate\Support\Str::limit($prompt, 120),
            ]);

            // Step 2: Generate and save the image
            $imageStart = microtime(true);
            $asset = $imageGenerator->generate($prompt, $spaceId, $size, $style);
            $imageLatency = (int) ((microtime(true) - $imageStart) * 1000);

            // Step 3: Attach to content as hero image
            $content->update(['hero_image_id' => $asset->id]);

            // Step 4: Log the image generation cost
            $costUsd = $asset->ai_metadata['cost_usd'] ?? 0.08;
            AIGenerationLog::create([
                'pipeline_run_id' => $this->run->id,
                'model' => 'dall-e-3',
                'purpose' => 'image_generation',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response' => json_encode([
                    'asset_id' => $asset->id,
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
                    'model' => 'dall-e-3',
                    'size' => $size,
                    'style' => $style,
                    'prompt_latency' => $promptLatency,
                    'asset_path' => $asset->path,
                ],
            ]);

            Log::info('GenerateImage: hero image attached', [
                'content_id' => $content->id,
                'asset_id' => $asset->id,
            ]);

            // Advance pipeline
            $executor->advance($this->run, [
                'stage' => $this->stage['name'],
                'success' => true,
                'asset_id' => $asset->id,
                'prompt' => $prompt,
                'summary' => "Generated hero image ({$size}) for \"{$title}\"",
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
}
