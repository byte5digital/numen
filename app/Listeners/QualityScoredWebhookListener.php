<?php

namespace App\Listeners;

use App\Events\Quality\ContentQualityScored;
use App\Services\Webhooks\WebhookEventDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class QualityScoredWebhookListener implements ShouldQueue
{
    public function __construct(private readonly WebhookEventDispatcher $dispatcher) {}

    public function handle(ContentQualityScored $event): void
    {
        $score = $event->score;

        $this->dispatcher->dispatch(
            'quality.scored',
            $score->space_id,
            [
                'score_id' => $score->id,
                'content_id' => $score->content_id,
                'space_id' => $score->space_id,
                'overall_score' => $score->overall_score,
                'readability_score' => $score->readability_score,
                'seo_score' => $score->seo_score,
                'brand_score' => $score->brand_score,
                'factual_score' => $score->factual_score,
                'engagement_score' => $score->engagement_score,
                'scored_at' => $score->scored_at->toIso8601String(),
            ]
        );
    }
}
