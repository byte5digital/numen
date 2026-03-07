<?php

use App\Http\Controllers\Api\BriefController;
use App\Http\Controllers\Api\ComponentDefinitionController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\ContentTaxonomyController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Api\TaxonomyTermController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Numen Public Content Delivery API
|--------------------------------------------------------------------------
|
| These routes serve published content to any frontend consumer.
| Public read endpoints require no authentication.
| Management endpoints require Sanctum authentication.
|
*/

Route::prefix('v1')->group(function () {

    // Content delivery (read-only, public)
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/content', [ContentController::class, 'index']);
        Route::get('/content/{slug}', [ContentController::class, 'show']);
        Route::get('/content/type/{type}', [ContentController::class, 'byType']);
        Route::get('/content/{slug}/terms', [ContentTaxonomyController::class, 'terms']);

        // Pages API (read-only, public) — headless delivery
        Route::get('/pages', [PageController::class, 'index']);
        Route::get('/pages/{slug}', [PageController::class, 'show']);

        // Taxonomy read (public)
        Route::get('/taxonomies', [TaxonomyController::class, 'index']);
        Route::get('/taxonomies/{vocabSlug}', [TaxonomyController::class, 'show']);
        Route::get('/taxonomies/{vocabSlug}/terms', [TaxonomyTermController::class, 'index']);
        Route::get('/taxonomies/{vocabSlug}/terms/{termSlug}', [TaxonomyTermController::class, 'show']);
        Route::get('/taxonomies/{vocabSlug}/terms/{termSlug}/content', [TaxonomyTermController::class, 'content']);
    });

    // Component type definitions (public read) — tighter limit
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/component-types', [ComponentDefinitionController::class, 'index']);
        Route::get('/component-types/{type}', [ComponentDefinitionController::class, 'show']);
    });

    // Management API (write operations — bearer token required)
    Route::middleware('auth:sanctum')->group(function () {

        // Taxonomy management
        Route::post('/taxonomies', [TaxonomyController::class, 'store']);
        Route::put('/taxonomies/{id}', [TaxonomyController::class, 'update']);
        Route::delete('/taxonomies/{id}', [TaxonomyController::class, 'destroy']);

        Route::post('/taxonomies/{vocabId}/terms', [TaxonomyTermController::class, 'store']);
        Route::put('/terms/{id}', [TaxonomyTermController::class, 'update']);
        Route::delete('/terms/{id}', [TaxonomyTermController::class, 'destroy']);
        Route::post('/terms/{id}/move', [TaxonomyTermController::class, 'move']);
        Route::post('/terms/reorder', [TaxonomyTermController::class, 'reorder']);

        Route::post('/content/{id}/terms', [ContentTaxonomyController::class, 'assign']);
        Route::put('/content/{id}/terms', [ContentTaxonomyController::class, 'sync']);
        Route::delete('/content/{id}/terms/{termId}', [ContentTaxonomyController::class, 'remove']);
        Route::post('/content/{id}/auto-categorize', [ContentTaxonomyController::class, 'autoCategorize']);

        // Component type registration (AI agents register new block types here)
        Route::post('/component-types', [ComponentDefinitionController::class, 'store']);
        Route::put('/component-types/{type}', [ComponentDefinitionController::class, 'update']);

        // Briefs (tighter rate limit on creation — cost-abuse prevention)
        Route::post('/briefs', [BriefController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/briefs', [BriefController::class, 'index']);
        Route::get('/briefs/{id}', [BriefController::class, 'show']);

        // Pipeline management
        Route::get('/pipeline-runs/{id}', function (string $id) {
            $run = \App\Models\PipelineRun::with(['content.currentVersion', 'brief', 'generationLogs'])
                ->findOrFail($id);

            return response()->json(['data' => $run]);
        });

        Route::post('/pipeline-runs/{id}/approve', function (string $id) {
            $run = \App\Models\PipelineRun::findOrFail($id);
            if ($run->status !== 'paused_for_review') {
                return response()->json(['error' => 'Run is not awaiting review'], 422);
            }
            app(\App\Pipelines\PipelineExecutor::class)->advance($run, [
                'stage' => $run->current_stage,
                'success' => true,
                'summary' => 'Approved by human reviewer',
            ]);

            return response()->json(['data' => ['status' => 'approved']]);
        });

        // Personas
        Route::get('/personas', function () {
            return response()->json(['data' => \App\Models\Persona::where('is_active', true)->get()]);
        });

        // Analytics
        Route::get('/analytics/costs', function () {
            $logs = \App\Models\AIGenerationLog::selectRaw('
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
        });
    });
});
