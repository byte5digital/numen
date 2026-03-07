<?php

use App\Http\Controllers\Api\BriefController;
use App\Http\Controllers\Api\ComponentDefinitionController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\ContentTaxonomyController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Api\TaxonomyTermController;
use App\Http\Controllers\Api\V1\Admin\SearchAdminController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\Versioning\AutoSaveController;
use App\Http\Controllers\Api\Versioning\DiffController;
use App\Http\Controllers\Api\Versioning\VersionController;
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

    // ── Search API (public, rate-limited) ──────────────────────────────────
    Route::prefix('search')->middleware('throttle:60,1')->group(function () {
        Route::get('/', [SearchController::class, 'search']);
        Route::get('/suggest', [SearchController::class, 'suggest'])->middleware('throttle:120,1');
        Route::post('/click', [SearchController::class, 'recordClick'])->middleware('throttle:30,1');
    });

    // Ask / RAG (lower rate limit — LLM calls are expensive)
    Route::post('/search/ask', [SearchController::class, 'ask'])->middleware('throttle:10,1');

    // ── Admin Search API (authenticated + admin role) ──────────────────────
    Route::prefix('admin/search')->middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/analytics', [SearchAdminController::class, 'analytics']);
        Route::get('/analytics/gaps', [SearchAdminController::class, 'contentGaps']);

        Route::get('/synonyms', [SearchAdminController::class, 'synonyms']);
        Route::post('/synonyms', [SearchAdminController::class, 'storeSynonym']);
        Route::put('/synonyms/{id}', [SearchAdminController::class, 'updateSynonym']);
        Route::delete('/synonyms/{id}', [SearchAdminController::class, 'destroySynonym']);

        Route::get('/promoted', [SearchAdminController::class, 'promoted']);
        Route::post('/promoted', [SearchAdminController::class, 'storePromoted']);
        Route::put('/promoted/{id}', [SearchAdminController::class, 'updatePromoted']);
        Route::delete('/promoted/{id}', [SearchAdminController::class, 'destroyPromoted']);

        Route::get('/health', [SearchAdminController::class, 'health']);
        Route::post('/reindex', [SearchAdminController::class, 'reindex']);
    });

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

        // Taxonomy management (rate-limited to prevent abuse)
        Route::middleware('throttle:60,1')->group(function () {
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
        });

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

        // Content Versioning (read endpoints — standard rate limit)
        Route::middleware('throttle:60,1')->prefix('content/{content}/versions')->group(function () {
            Route::get('/', [VersionController::class, 'index']);
            Route::get('/{version}', [VersionController::class, 'show']);
        });

        // Content Versioning (write/publish endpoints — tighter rate limit)
        Route::middleware('throttle:30,1')->prefix('content/{content}/versions')->group(function () {
            Route::post('/draft', [VersionController::class, 'createDraft']);
            Route::patch('/{version}', [VersionController::class, 'update']);
            Route::post('/{version}/label', [VersionController::class, 'label']);
            Route::post('/{version}/publish', [VersionController::class, 'publish']);
            Route::post('/{version}/schedule', [VersionController::class, 'schedule']);
            Route::delete('/{version}/schedule', [VersionController::class, 'cancelSchedule']);
            Route::post('/{version}/rollback', [VersionController::class, 'rollback']);
            Route::post('/{version}/branch', [VersionController::class, 'branch']);
        });

        // Version diff
        Route::middleware('throttle:30,1')->get('/content/{content}/diff', [DiffController::class, 'compare']);

        // Auto-save drafts — Fix 3: 30 saves/minute per user to prevent abuse
        Route::prefix('content/{content}/autosave')->group(function () {
            Route::post('/', [AutoSaveController::class, 'save'])->middleware('throttle:30,1');
            Route::get('/', [AutoSaveController::class, 'show']);
            Route::delete('/', [AutoSaveController::class, 'discard']);
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
