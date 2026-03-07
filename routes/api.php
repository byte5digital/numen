<?php

use App\Http\Controllers\Api\BriefController;
use App\Http\Controllers\Api\ComponentDefinitionController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
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

        // Pages API (read-only, public) — headless delivery
        Route::get('/pages', [PageController::class, 'index']);
        Route::get('/pages/{slug}', [PageController::class, 'show']);
    });

    // Component type definitions (public read, authenticated write) — tighter limit
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/component-types', [ComponentDefinitionController::class, 'index']);
        Route::get('/component-types/{type}', [ComponentDefinitionController::class, 'show']);
    });

    // Management API (authenticated)
    Route::middleware('auth:sanctum')->group(function () {

        // Content management (write endpoints)
        Route::post('/content', [ContentController::class, 'store']);
        Route::put('/content/{id}', [ContentController::class, 'update']);
        Route::delete('/content/{id}', [ContentController::class, 'destroy']);

        // User management
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        // Role management
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);

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
