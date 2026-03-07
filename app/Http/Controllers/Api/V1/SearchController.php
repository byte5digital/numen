<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\AskRequest;
use App\Http\Requests\Search\ClickRequest;
use App\Http\Requests\Search\SearchRequest;
use App\Http\Requests\Search\SuggestRequest;
use App\Services\Search\AskQuery;
use App\Services\Search\SearchAnalyticsRecorder;
use App\Services\Search\SearchQuery;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Public search API endpoints.
 * Rate-limited at the router level.
 */
class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $search,
        private readonly SearchAnalyticsRecorder $analytics,
    ) {}

    public function search(SearchRequest $request): JsonResponse
    {
        /** @var array<string,string> $taxonomy */
        $taxonomy = (array) $request->input('taxonomy', []);

        $query = new SearchQuery(
            query: (string) $request->input('q'),
            spaceId: (string) ($request->input('space_id') ?? config('numen.default_space', 'default')),
            mode: (string) ($request->input('mode', 'hybrid')),
            contentType: $request->input('type') ? (string) $request->input('type') : null,
            locale: $request->input('locale') ? (string) $request->input('locale') : null,
            taxonomyFilters: $taxonomy,
            dateFrom: $request->input('date_from') ? (string) $request->input('date_from') : null,
            dateTo: $request->input('date_to') ? (string) $request->input('date_to') : null,
            page: (int) $request->input('page', 1),
            perPage: (int) $request->input('per_page', 20),
            highlight: (bool) $request->input('highlight', true),
        );

        $results = $this->search->search($query);

        return response()->json($results->toArray());
    }

    public function suggest(SuggestRequest $request): JsonResponse
    {
        $prefix = (string) $request->input('q');
        $spaceId = (string) ($request->input('space_id') ?? config('numen.default_space', 'default'));
        $limit = (int) $request->input('limit', 5);

        $suggestions = $this->search->suggest($prefix, $spaceId, $limit);

        return response()->json(['suggestions' => $suggestions]);
    }

    public function ask(AskRequest $request): JsonResponse
    {
        $query = new AskQuery(
            question: (string) $request->input('question'),
            spaceId: (string) ($request->input('space_id') ?? config('numen.default_space', 'default')),
            conversationId: $request->input('conversation_id') ? (string) $request->input('conversation_id') : null,
            locale: $request->input('locale') ? (string) $request->input('locale') : null,
            sessionId: $request->hasSession() ? $request->session()->getId() : null,
        );

        $response = $this->search->ask($query);

        return response()->json($response->toArray());
    }

    public function recordClick(ClickRequest $request): Response
    {
        $this->analytics->recordClick(
            spaceId: (string) ($request->input('space_id') ?? config('numen.default_space', 'default')),
            query: (string) $request->input('query'),
            contentId: (string) $request->input('content_id'),
            position: (int) $request->input('position'),
            sessionId: $request->input('session_id') ? (string) $request->input('session_id') : null,
        );

        return response()->noContent();
    }
}
