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
        $space = app()->bound('current_space')
            ? app('current_space')
            : ($request->has('space_id') ? Space::find($request->input('space_id')) : null);

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
            'initialLeaderboard' => $leaderboard,
            'initialDistribution' => $distribution,
        ]);
    }
}
