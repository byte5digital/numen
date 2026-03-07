<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AIGenerationLog;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private AuthorizationService $authz) {}

    /**
     * AI generation cost analytics. Requires audit.view permission.
     * Contains financial data (spend per model/purpose) — restrict appropriately.
     */
    public function costs(Request $request): JsonResponse
    {
        $this->authz->authorize($request->user(), 'audit.view');

        $logs = AIGenerationLog::selectRaw('
            DATE(created_at) as date,
            model,
            purpose,
            COUNT(*) as calls,
            SUM(input_tokens) as total_input_tokens,
            SUM(output_tokens) as total_output_tokens,
            SUM(cost_usd) as total_cost
        ')
            ->groupBy('date', 'model', 'purpose')
            ->orderByDesc('date')
            ->limit(100)
            ->get();

        return response()->json(['data' => $logs]);
    }
}
