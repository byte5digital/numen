<?php

namespace App\Jobs;

use App\Models\Content;
use App\Models\FormatTemplate;
use App\Models\Persona;
use App\Models\RepurposedContent;
use App\Services\AI\LLMManager;
use App\Services\FormatAdapterService;
use App\Services\FormatTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RepurposeContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'ai-pipeline';

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly RepurposedContent $repurposedContent,
    ) {}

    public function handle(
        FormatAdapterService $formatAdapter,
        FormatTemplateService $templateService,
        LLMManager $llm,
    ): void {
        $item = $this->repurposedContent;

        // 1. Mark as processing
        $item->update(['status' => RepurposedContent::STATUS_PROCESSING]);

        // 2. Load source Content + FormatTemplate
        $content = Content::with('currentVersion')->findOrFail($item->source_content_id);

        $template = $item->format_template_id
            ? FormatTemplate::findOrFail($item->format_template_id)
            : $templateService->getForSpace($item->space_id, $item->format_key);

        if (! $template) {
            throw new \RuntimeException("No FormatTemplate found for format_key={$item->format_key} space_id={$item->space_id}");
        }

        $persona = $item->persona_id ? Persona::find($item->persona_id) : null;

        // 3. Build prompt
        $prompt = $formatAdapter->buildPrompt($content, $template, $persona);

        // 4. Generate via LLMManager
        $response = $llm->complete([
            'model' => config('ai.default_model', 'claude-3-5-haiku-20241022'),
            'system' => $prompt['system'],
            'messages' => [
                ['role' => 'user', 'content' => $prompt['user']],
            ],
            'max_tokens' => $template->max_tokens ?: 2048,
        ]);

        // 5. Parse output
        $parsed = $formatAdapter->parseOutput($response->content, $template);

        // 6. Update record
        $item->update([
            'status' => RepurposedContent::STATUS_COMPLETED,
            'output' => $parsed['output'],
            'output_parts' => $parsed['output_parts'] ?? null,
            'tokens_used' => $response->totalTokens(),
            'repurposed_at' => now(),
        ]);

        // Increment batch completed counter if part of a batch
        if ($item->batch_id) {
            $item->batch()->increment('completed_items');
            $this->maybeFinalizeBatch($item);
        }

        Log::info('RepurposeContentJob completed', [
            'repurposed_content_id' => $item->id,
            'format_key' => $item->format_key,
            'tokens_used' => $response->totalTokens(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $item = $this->repurposedContent;

        $item->update([
            'status' => RepurposedContent::STATUS_FAILED,
            'error_message' => substr($e->getMessage(), 0, 500),
        ]);

        if ($item->batch_id) {
            $item->batch()->increment('failed_items');
            $this->maybeFinalizeBatch($item);
        }

        Log::error('RepurposeContentJob failed', [
            'repurposed_content_id' => $item->id,
            'format_key' => $item->format_key,
            'error' => $e->getMessage(),
        ]);
    }

    private function maybeFinalizeBatch(RepurposedContent $item): void
    {
        /** @var \App\Models\RepurposingBatch|null $batch */
        $batch = $item->batch()->lockForUpdate()->first();

        if (! $batch instanceof \App\Models\RepurposingBatch) {
            return;
        }

        $done = $batch->completed_items + $batch->failed_items;

        if ($done >= $batch->total_items) {
            $batch->update([
                'status' => $batch->failed_items > 0 && $batch->completed_items === 0
                    ? 'failed'
                    : 'completed',
                'completed_at' => now(),
            ]);
        }
    }
}
