<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BriefController;
use App\Http\Controllers\Api\ComponentDefinitionController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PersonaController;
use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserRoleController;
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

        // User-role assignment
        Route::get('/roles/{role}/users', [UserRoleController::class, 'roleUsers']);
        Route::post('/users/{user}/roles', [UserRoleController::class, 'assignRole']);
        Route::delete('/users/{user}/roles/{role}', [UserRoleController::class, 'revokeRole']);
        Route::get('/users/{user}/roles', [UserRoleController::class, 'userRoles']);

        // Audit logs
        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        // Permission taxonomy (read-only, for admin UI / role editor)
        Route::get('/permissions', [PermissionController::class, 'index']);

        // Component type registration (AI agents register new block types here)
        Route::post('/component-types', [ComponentDefinitionController::class, 'store']);
        Route::put('/component-types/{type}', [ComponentDefinitionController::class, 'update']);

        // Briefs (tighter rate limit on creation — cost-abuse prevention)
        Route::post('/briefs', [BriefController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/briefs', [BriefController::class, 'index']);
        Route::get('/briefs/{id}', [BriefController::class, 'show']);

        // Pipeline management
        Route::get('/pipeline-runs/{id}', [PipelineController::class, 'show']);
        Route::post('/pipeline-runs/{id}/approve', [PipelineController::class, 'approve']);

        // Personas — restricted: contains system prompts and model assignments
        Route::get('/personas', [PersonaController::class, 'index']);

        // Analytics — restricted: contains financial spend data
        Route::get('/analytics/costs', [AnalyticsController::class, 'costs']);
    });
});
