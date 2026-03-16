<?php

namespace App\Jobs;

use App\Models\CompetitorContentItem;
use App\Services\Competitor\CompetitorAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckCompetitorAlertsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly string $competitorContentId,
    ) {}

    public function handle(CompetitorAlertService $alertService): void
    {
        $item = CompetitorContentItem::find($this->competitorContentId);

        if (! $item) {
            Log::warning('CheckCompetitorAlertsJob: content item not found', [
                'id' => $this->competitorContentId,
            ]);

            return;
        }

        Log::info('CheckCompetitorAlertsJob: evaluating alerts', [
            'competitor_content_id' => $item->id,
        ]);

        $alertService->evaluate($item);
    }

    public function tags(): array
    {
        return ['competitor', 'alerts', "content:{$this->competitorContentId}"];
    }
}
