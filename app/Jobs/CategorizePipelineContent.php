<?php

namespace App\Jobs;

use App\Models\Content;
use App\Services\Taxonomy\TaxonomyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CategorizePipelineContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Content $content,
    ) {
        $this->onQueue('ai-pipeline');
    }

    public function handle(TaxonomyService $taxonomy): void
    {
        Log::info('CategorizePipelineContent: starting auto-categorization', [
            'content_id' => $this->content->id,
        ]);

        // Load space vocabularies
        $vocabularies = $this->content->space->vocabularies()->with('terms')->get();

        if ($vocabularies->isEmpty()) {
            Log::info('CategorizePipelineContent: no vocabularies for space, skipping', [
                'space_id' => $this->content->space_id,
            ]);

            return;
        }

        // Future: integrate TaxonomyCategorizationService (AI) here.
        // For now, this job acts as a hook point that can be wired to the
        // categorization service when an LLM provider is configured.
        Log::info('CategorizePipelineContent: complete (no AI provider configured for categorization)', [
            'content_id' => $this->content->id,
            'vocabularies' => $vocabularies->pluck('slug'),
        ]);
    }
}
