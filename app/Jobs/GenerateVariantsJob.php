<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Services\MediaTransformService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public readonly MediaAsset $asset) {}

    public function handle(MediaTransformService $transformService): void
    {
        $variants = $transformService->generateVariants($this->asset);

        $this->asset->update([
            'metadata' => array_merge($this->asset->metadata ?? [], ['variants' => $variants]),
        ]);
    }
}
