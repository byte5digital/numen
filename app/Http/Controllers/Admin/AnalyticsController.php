<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AIGenerationLog;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index()
    {
        $dailyCosts = AIGenerationLog::selectRaw('DATE(created_at) as date, SUM(cost_usd) as cost')
            ->groupBy('date')
            ->orderByDesc('date')
            ->limit(30)
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'cost' => (float) $r->cost]);

        $modelBreakdown = AIGenerationLog::selectRaw('model, COUNT(*) as calls, SUM(cost_usd) as total_cost')
            ->groupBy('model')
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn ($r) => [
                'model' => $r->model,
                'calls' => $r->calls,
                'total_cost' => (float) $r->total_cost,
            ]);

        // Cost by purpose (text generation vs image generation vs SEO etc.)
        $purposeBreakdown = AIGenerationLog::selectRaw('purpose, COUNT(*) as calls, SUM(cost_usd) as total_cost')
            ->groupBy('purpose')
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn ($r) => [
                'purpose' => $r->purpose,
                'calls' => $r->calls,
                'total_cost' => (float) $r->total_cost,
            ]);

        $totals = AIGenerationLog::selectRaw('
            SUM(cost_usd) as total_cost,
            COUNT(*) as total_calls,
            SUM(input_tokens) as total_input,
            SUM(output_tokens) as total_output
        ')->first();

        // Image-specific totals
        $imageTotals = AIGenerationLog::where('purpose', 'image_generation')
            ->selectRaw('COUNT(*) as count, SUM(cost_usd) as cost')
            ->first();

        return Inertia::render('Analytics/Index', [
            'dailyCosts' => $dailyCosts,
            'modelBreakdown' => $modelBreakdown,
            'purposeBreakdown' => $purposeBreakdown,
            'totalCost' => (float) ($totals->total_cost ?? 0),
            'totalCalls' => (int) ($totals->total_calls ?? 0),
            'totalTokens' => [
                'input' => (int) ($totals->total_input ?? 0),
                'output' => (int) ($totals->total_output ?? 0),
            ],
            'imageTotals' => [
                'count' => (int) ($imageTotals->count ?? 0),
                'cost' => (float) ($imageTotals->cost ?? 0),
            ],
        ]);
    }
}
