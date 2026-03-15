<?php

namespace App\Services;

use App\Jobs\RepurposeBatchJob;
use App\Jobs\RepurposeContentJob;
use App\Models\Content;
use App\Models\Persona;
use App\Models\RepurposedContent;
use App\Models\RepurposingBatch;
use App\Models\Space;
use Illuminate\Support\Collection;
use RuntimeException;

class RepurposingService
{
    /** Maximum items allowed in a single batch (cost guard). */
    public const BATCH_LIMIT = 50;

    /** Average tokens used per content item (used for cost estimation). */
    private const TOKENS_PER_ITEM = 2000;

    /** Approximate input cost per million tokens for the estimate model (USD). */
    private const ESTIMATE_INPUT_COST_PER_M = 3.0;

    /** Approximate output cost per million tokens for the estimate model (USD). */
    private const ESTIMATE_OUTPUT_COST_PER_M = 15.0;

    public function __construct(
        private readonly FormatTemplateService $templateService,
    ) {}

    /**
     * Queue a single content item for repurposing.
     *
     * Creates a RepurposedContent record (status=pending) and dispatches
     * RepurposeContentJob to the ai-pipeline queue. Caller should poll the
     * returned record's status field.
     */
    public function repurpose(Content $content, string $formatKey, ?Persona $persona = null): RepurposedContent
    {
        $template = $this->templateService->getForSpace((int) $content->space_id, $formatKey);

        $item = RepurposedContent::create([
            'space_id' => $content->space_id,
            'source_content_id' => $content->id,
            'format_template_id' => $template?->id,
            'format_key' => $formatKey,
            'status' => RepurposedContent::STATUS_PENDING,
            'persona_id' => $persona?->id,
        ]);

        RepurposeContentJob::dispatch($item)->onQueue('ai-pipeline');

        return $item;
    }

    /**
     * Queue all published content in a space for batch repurposing.
     *
     * Enforces a 50-item hard limit to guard against runaway costs.
     * Creates a RepurposingBatch record and dispatches RepurposeBatchJob.
     *
     * @throws RuntimeException when published content exceeds BATCH_LIMIT
     */
    public function repurposeBatch(
        Space $space,
        string $formatKey,
        ?Persona $persona = null,
        int $limit = self::BATCH_LIMIT,
    ): RepurposingBatch {
        $count = Content::query()
            ->where('space_id', $space->id)
            ->published()
            ->count();

        if ($count > $limit) {
            throw new RuntimeException(
                "Batch limit exceeded: space contains {$count} published items, but the limit is {$limit}. "
                .'Use a filtered scope or increase the limit to proceed.'
            );
        }

        $batch = RepurposingBatch::create([
            'space_id' => $space->id,
            'format_key' => $formatKey,
            'status' => RepurposingBatch::STATUS_PENDING,
            'total_items' => $count,
            'completed_items' => 0,
            'failed_items' => 0,
            'persona_id' => $persona?->id,
        ]);

        RepurposeBatchJob::dispatch($batch)->onQueue('ai-pipeline');

        return $batch;
    }

    /**
     * Return the current status string of a repurposed-content item.
     */
    public function getStatus(RepurposedContent $item): string
    {
        return $item->refresh()->status;
    }

    /**
     * Return all repurposed versions for a given piece of source content.
     *
     * @return Collection<int, RepurposedContent>
     */
    public function getResults(Content $content): Collection
    {
        return RepurposedContent::query()
            ->where('source_content_id', $content->id)
            ->latest()
            ->get();
    }

    /**
     * Estimate the cost of repurposing all published content in a space.
     *
     * Returns an array with:
     *   - items              int    Number of published content items
     *   - estimated_tokens   int    Rough total token estimate
     *   - estimated_cost_usd float  Rough USD cost at default model pricing
     *
     * @return array{items: int, estimated_tokens: int, estimated_cost_usd: float}
     */
    public function estimateCost(Space $space, string $formatKey): array
    {
        $items = Content::query()
            ->where('space_id', $space->id)
            ->published()
            ->count();

        $totalTokens = $items * self::TOKENS_PER_ITEM;

        // Assume a 70/30 input/output split.
        $inputTokens = (int) ($totalTokens * 0.70);
        $outputTokens = (int) ($totalTokens * 0.30);

        $cost = ($inputTokens * self::ESTIMATE_INPUT_COST_PER_M
            + $outputTokens * self::ESTIMATE_OUTPUT_COST_PER_M)
            / 1_000_000;

        return [
            'items' => $items,
            'estimated_tokens' => $totalTokens,
            'estimated_cost_usd' => round($cost, 6),
        ];
    }
}
