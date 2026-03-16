<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Services\Quality\QualityTrendAggregator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QualityDashboardController extends Controller
{
    public function __construct(private readonly QualityTrendAggregator $aggregator) {}

    public function index(Request $request): Response
    {
        /** @var Space|null $space */
        $space = Space::first();

        if ($space === null) {
            return Inertia::render('Quality/Dashboard', [
                'spaceId' => '',
                'spaceName' => '',
                'initialTrends' => [],
                'initialLeaderboard' => [],
                'initialDistribution' => [],
            ]);
        }

        $from = Carbon::now()->subDays(30);
        $to = Carbon::now();

        $trends = $this->aggregator->getSpaceTrends($space, $from, $to);
        $leaderboard = $this->aggregator->getSpaceLeaderboard($space, 10);
        $distribution = $this->aggregator->getDimensionDistribution($space);

        return Inertia::render('Quality/Dashboard', [
            'spaceId' => $space->id,
            'spaceName' => $space->name,
            'initialTrends' => $trends,
            'initialLeaderboard' => $leaderboard->map(fn ($s) => [
                'score_id' => $s->id,
                'content_id' => $s->content_id,
                'title' => $s->content->currentVersion?->title,
                'overall_score' => $s->overall_score,
                'scored_at' => $s->scored_at->toIso8601String(),
            ]),
            'initialDistribution' => $distribution,
        ]);
    }
}
