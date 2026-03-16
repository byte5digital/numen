<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContentQualityConfigResource;
use App\Http\Resources\ContentQualityScoreResource;
use App\Jobs\ScoreContentQualityJob;
use App\Models\Content;
use App\Models\ContentQualityConfig;
use App\Models\ContentQualityScore;
use App\Models\Space;
use App\Services\AuthorizationService;
use App\Services\Quality\QualityTrendAggregator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ContentQualityController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authz,
        private readonly QualityTrendAggregator $trendAggregator,
    ) {}

    /**
     * GET /api/v1/quality/scores?space_id=&content_id=&per_page=
     * List quality scores (optionally filtered by content_id).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string', 'exists:spaces,id'],
            'content_id' => ['sometimes', 'string', 'exists:contents,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $this->authz->authorize($user, 'content.view', $validated['space_id']);

        $query = ContentQualityScore::with('items')
            ->where('space_id', $validated['space_id'])
            ->latest('scored_at');

        if (isset($validated['content_id'])) {
            $query->where('content_id', $validated['content_id']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);

        return ContentQualityScoreResource::collection($query->paginate($perPage));
    }

    /**
     * GET /api/v1/quality/scores/{score}
     * Get a single quality score with its items.
     */
    public function show(Request $request, ContentQualityScore $score): ContentQualityScoreResource
    {
        $user = $request->user();
        $this->authz->authorize($user, 'content.view', $score->space_id);

        $score->load('items');

        return new ContentQualityScoreResource($score);
    }

    /**
     * POST /api/v1/quality/score
     * Trigger a quality scoring job for a content item.
     */
    public function score(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content_id' => ['required', 'string', 'exists:contents,id'],
        ]);

        /** @var Content $content */
        $content = Content::findOrFail($validated['content_id']);

        $user = $request->user();
        $this->authz->authorize($user, 'content.view', $content->space_id);

        ScoreContentQualityJob::dispatch($content->id);

        return response()->json([
            'message' => 'Quality scoring job queued.',
            'content_id' => $content->id,
        ], 202);
    }

    /**
     * GET /api/v1/quality/trends?space_id=&from=&to=
     * Aggregate daily trend data for a space.
     */
    public function trends(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string', 'exists:spaces,id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $user = $request->user();
        $this->authz->authorize($user, 'content.view', $validated['space_id']);

        /** @var Space $space */
        $space = Space::findOrFail($validated['space_id']);

        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : now()->subDays(30);
        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : now();

        if ($from->diffInDays($to) > 365) {
            return response()->json([
                'message' => 'Date range must not exceed 365 days.',
            ], 422);
        }

        $trends = $this->trendAggregator->getSpaceTrends($space, $from, $to);
        $leaderboard = $this->trendAggregator->getSpaceLeaderboard($space, 10);
        $distribution = $this->trendAggregator->getDimensionDistribution($space);

        return response()->json([
            'data' => [
                'trends' => $trends,
                'leaderboard' => $leaderboard->map(fn (ContentQualityScore $s) => [
                    'score_id' => $s->id,
                    'content_id' => $s->content_id,
                    'title' => $s->content->currentVersion?->title,
                    'overall_score' => $s->overall_score,
                    'scored_at' => $s->scored_at->toIso8601String(),
                ]),
                'distribution' => $distribution,
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/quality/config?space_id=
     * Get quality config for a space (or default if not configured).
     */
    public function getConfig(Request $request): ContentQualityConfigResource
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string', 'exists:spaces,id'],
        ]);

        $user = $request->user();
        $this->authz->authorize($user, 'content.view', $validated['space_id']);

        $config = ContentQualityConfig::firstOrCreate(
            ['space_id' => $validated['space_id']],
            [
                'dimension_weights' => [
                    'readability' => 0.25,
                    'seo' => 0.25,
                    'brand_consistency' => 0.20,
                    'factual_accuracy' => 0.15,
                    'engagement_prediction' => 0.15,
                ],
                'thresholds' => [
                    'poor' => 40,
                    'fair' => 60,
                    'good' => 75,
                    'excellent' => 90,
                ],
                'enabled_dimensions' => ['readability', 'seo', 'brand_consistency', 'factual_accuracy', 'engagement_prediction'],
                'auto_score_on_publish' => true,
                'pipeline_gate_enabled' => false,
                'pipeline_gate_min_score' => 70.0,
            ]
        );

        return new ContentQualityConfigResource($config);
    }

    /**
     * PUT /api/v1/quality/config
     * Update quality config for a space.
     */
    public function updateConfig(Request $request): ContentQualityConfigResource
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string', 'exists:spaces,id'],
            'dimension_weights' => ['sometimes', 'array'],
            'dimension_weights.*' => ['numeric', 'min:0', 'max:1'],
            'thresholds' => ['sometimes', 'array'],
            'thresholds.*' => ['numeric', 'min:0', 'max:100'],
            'enabled_dimensions' => ['sometimes', 'array'],
            'enabled_dimensions.*' => [
                'string',
                Rule::in(['readability', 'seo', 'brand_consistency', 'factual_accuracy', 'engagement_prediction']),
            ],
            'auto_score_on_publish' => ['sometimes', 'boolean'],
            'pipeline_gate_enabled' => ['sometimes', 'boolean'],
            'pipeline_gate_min_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        $user = $request->user();
        $this->authz->authorize($user, 'settings.manage', $validated['space_id']);

        $defaults = [
            'dimension_weights' => [
                'readability' => 0.25,
                'seo' => 0.25,
                'brand_consistency' => 0.20,
                'factual_accuracy' => 0.15,
                'engagement_prediction' => 0.15,
            ],
            'thresholds' => ['poor' => 40, 'fair' => 60, 'good' => 75, 'excellent' => 90],
            'enabled_dimensions' => ['readability', 'seo', 'brand_consistency', 'factual_accuracy', 'engagement_prediction'],
            'auto_score_on_publish' => true,
            'pipeline_gate_enabled' => false,
            'pipeline_gate_min_score' => 70.0,
        ];

        $config = ContentQualityConfig::firstOrNew(
            ['space_id' => $validated['space_id']],
            array_merge($defaults, ['space_id' => $validated['space_id']]),
        );

        $updates = array_filter($validated, fn ($v, $k) => $k !== 'space_id', ARRAY_FILTER_USE_BOTH);
        $config->fill($updates);
        $config->save();

        return new ContentQualityConfigResource($config);
    }
}
