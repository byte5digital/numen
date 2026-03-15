<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Services\MediaTaggingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoTagMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'ai-pipeline';

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly MediaAsset $asset) {}

    public function handle(MediaTaggingService $taggingService): void
    {
        if (! $taggingService->isEnabled()) {
            return;
        }

        $tags = $taggingService->generateTags($this->asset);
        $taggingService->applyTags($this->asset, $tags);
    }
}
