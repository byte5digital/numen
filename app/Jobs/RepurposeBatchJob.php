<?php

namespace App\Jobs;

use App\Models\Content;
use App\Models\RepurposedContent;
use App\Models\RepurposingBatch;
use App\Services\RepurposingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RepurposeBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'ai-pipeline';

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly RepurposingBatch $batch,
        public readonly ?int $personaId = null,
    ) {}

    public function handle(RepurposingService $service): void
    {
        $batch = $this->batch;

        // 1. Mark batch as processing
        $batch->update([
            'status' => RepurposingBatch::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        // 2. Fetch all published Content for the space
        $contents = Content::query()
            ->where('space_id', $batch->space_id)
            ->published()
            ->get();

        $total = $contents->count();

        if ($total === 0) {
            $batch->update([
                'status' => RepurposingBatch::STATUS_COMPLETED,
                'total_items' => 0,
                'completed_at' => now(),
            ]);

            return;
        }

        // 3. For each content: create RepurposedContent (pending) + dispatch RepurposeContentJob
        foreach ($contents as $content) {
            $item = RepurposedContent::create([
                'space_id' => $batch->space_id,
                'source_content_id' => $content->id,
                'format_template_id' => null, // resolved at job handle time
                'batch_id' => $batch->id,
                'format_key' => $batch->format_key,
                'status' => RepurposedContent::STATUS_PENDING,
                'persona_id' => $this->personaId ?? $batch->persona_id,
            ]);

            RepurposeContentJob::dispatch($item)->onQueue('ai-pipeline');
        }

        // 4. Update batch.total_items
        $batch->update(['total_items' => $total]);

        Log::info('RepurposeBatchJob dispatched individual jobs', [
            'batch_id' => $batch->id,
            'total_items' => $total,
            'format_key' => $batch->format_key,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $this->batch->update([
            'status' => RepurposingBatch::STATUS_FAILED,
        ]);

        Log::error('RepurposeBatchJob failed', [
            'batch_id' => $this->batch->id,
            'error' => $e->getMessage(),
        ]);
    }
}
