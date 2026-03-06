<?php

namespace App\Jobs;

use App\Models\AIGenerationLog;
use App\Models\Content;
use App\Services\AI\ImageGenerator;
use App\Services\AI\ImagePromptBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateContentImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;
    public int $maxExceptions = 2;

    public function __construct(
        public Content $content,
        public string $size = '1792x1024',
        public string $style = 'vivid',
    ) {
        $this->onQueue('ai-pipeline');
    }

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(ImagePromptBuilder $promptBuilder, ImageGenerator $imageGenerator): void
    {
        Log::info('GenerateContentImage: starting', [
            'content_id' => $this->content->id,
            'size' => $this->size,
        ]);

        try {
            $version = $this->content->currentVersion;
            $title = $version?->title ?? 'Untitled';
            $excerpt = $version?->excerpt ?? '';
            $tags = $this->content->taxonomy['tags'] ?? [];
            $contentType = $this->content->contentType?->slug ?? 'blog_post';

            $prompt = $promptBuilder->build($title, $excerpt, $tags, $contentType);

            $imageStart = microtime(true);
            $asset = $imageGenerator->generate($prompt, $this->content->space_id, $this->size, $this->style);
            $imageLatency = (int) ((microtime(true) - $imageStart) * 1000);

            $this->content->update(['hero_image_id' => $asset->id]);

            // Log cost (find pipeline run if exists)
            $pipelineRunId = $this->content->pipelineRuns()?->latest()?->value('id');
            $costUsd = $asset->ai_metadata['cost_usd'] ?? 0.08;

            AIGenerationLog::create([
                'pipeline_run_id' => $pipelineRunId,
                'model'           => 'dall-e-3',
                'purpose'         => 'image_generation',
                'messages'        => [['role' => 'user', 'content' => $prompt]],
                'response'        => json_encode(['asset_id' => $asset->id, 'size' => $this->size]),
                'input_tokens'    => 0,
                'output_tokens'   => 0,
                'cost_usd'        => $costUsd,
                'latency_ms'      => $imageLatency,
                'stop_reason'     => 'complete',
                'metadata'        => ['type' => 'image', 'model' => 'dall-e-3', 'size' => $this->size, 'style' => $this->style],
            ]);

            Log::info('GenerateContentImage: completed', [
                'content_id' => $this->content->id,
                'asset_id' => $asset->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateContentImage: failed', [
                'content_id' => $this->content->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
