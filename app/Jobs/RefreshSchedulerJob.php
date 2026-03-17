<?php

namespace App\Jobs;

use App\Models\Space;
use App\Services\Performance\AutoBriefGeneratorService;
use App\Services\Performance\ContentRefreshAdvisorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshSchedulerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function handle(
        ContentRefreshAdvisorService $advisorService,
        AutoBriefGeneratorService $briefGenerator,
    ): void {
        $spaces = Space::all();

        foreach ($spaces as $space) {
            Log::info('RefreshSchedulerJob: analyzing space', ['space_id' => $space->id]);

            $suggestions = $advisorService->generateSuggestions($space->id);

            $autoRefreshEnabled = $space->settings['auto_refresh_enabled'] ?? false;

            if ($autoRefreshEnabled) {
                $highPriority = $suggestions->filter(
                    fn ($s) => $s->urgency_score >= 50 && $s->brief_id === null,
                );

                foreach ($highPriority as $suggestion) {
                    try {
                        $briefGenerator->generateRefreshBrief($suggestion);
                        Log::info('RefreshSchedulerJob: auto-generated brief', [
                            'suggestion_id' => $suggestion->id,
                            'content_id' => $suggestion->content_id,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('RefreshSchedulerJob: failed to generate brief', [
                            'suggestion_id' => $suggestion->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            Log::info('RefreshSchedulerJob: completed space', [
                'space_id' => $space->id,
                'suggestions_count' => $suggestions->count(),
            ]);
        }
    }
}
