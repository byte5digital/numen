<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BriefController;
use App\Http\Controllers\Api\ComponentDefinitionController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\ContentTaxonomyController;
use App\Http\Controllers\Api\FormatTemplateController;
use App\Http\Controllers\Api\MediaCollectionController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MediaEditController;
use App\Http\Controllers\Api\MediaFolderController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PluginAdminController;
use App\Http\Controllers\Api\PublicMediaController;
use App\Http\Controllers\Api\RepurposingController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Api\TaxonomyTermController;
use App\Http\Controllers\Api\UserRoleController;
use App\Http\Controllers\Api\V1\Admin\SearchAdminController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\Versioning\AutoSaveController;
use App\Http\Controllers\Api\Versioning\DiffController;
use App\Http\Controllers\Api\Versioning\VersionController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WebhookDeliveryController;
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

    // Taxonomy content listing (public read-only)
    Route::get('/taxonomies/{vocabSlug}/terms/{termSlug}/content', [TaxonomyTermController::class, 'content']);
    Route::get('/content/{slug}/terms', [ContentTaxonomyController::class, 'terms']);

    // Taxonomy content listing (public read-only)
    Route::get('/taxonomies/{vocabSlug}/terms/{termSlug}/content', [TaxonomyTermController::class, 'content']);
    Route::get('/content/{slug}/terms', [ContentTaxonomyController::class, 'terms']);

    // Search API (public search endpoint)
    Route::get('/search', [SearchController::class, 'search']);
    Route::get('/search/suggest', [SearchController::class, 'suggest']);
    Route::post('/search/ask', [SearchController::class, 'ask']);
    Route::post('/search/click', [SearchController::class, 'recordClick']);

    // Management API (authenticated)

    // Format templates — public endpoint
    Route::get('/format-templates/supported', [FormatTemplateController::class, 'supported']);

    Route::middleware('auth:sanctum')->group(function () {

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

        // Audit logs (requires audit.view permission)
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');

        // User roles (requires users.roles.assign or roles.manage)
        Route::post('/users/{user}/roles', [UserRoleController::class, 'assignRole'])->middleware('permission:users.roles.assign');
        Route::delete('/users/{user}/roles/{role}', [UserRoleController::class, 'revokeRole'])->middleware('permission:users.roles.assign');
        Route::get('/users/{user}/roles', [UserRoleController::class, 'userRoles']);
        Route::get('/roles/{role}/users', [UserRoleController::class, 'roleUsers'])->middleware('permission:roles.manage');

        // Create new content
        Route::post('/content', [ContentController::class, 'store'])->middleware('permission:content.create');

        // Taxonomies API
        Route::get('/taxonomies', [TaxonomyController::class, 'index']);
        Route::post('/taxonomies', [TaxonomyController::class, 'store'])->middleware('permission:content.create');
        Route::get('/taxonomies/{vocabSlug}', [TaxonomyController::class, 'show']);
        Route::put('/taxonomies/{id}', [TaxonomyController::class, 'update'])->middleware('permission:content.update');
        Route::delete('/taxonomies/{id}', [TaxonomyController::class, 'destroy'])->middleware('permission:content.delete');

        // Taxonomy Terms API
        Route::get('/taxonomies/{vocabSlug}/terms', [TaxonomyTermController::class, 'index']);
        Route::post('/taxonomies/{vocabId}/terms', [TaxonomyTermController::class, 'store'])->middleware('permission:content.create');
        Route::get('/taxonomies/{vocabSlug}/terms/{termSlug}', [TaxonomyTermController::class, 'show']);
        Route::put('/taxonomies/terms/{id}', [TaxonomyTermController::class, 'update'])->middleware('permission:content.update');
        Route::post('/taxonomies/terms/{id}/move', [TaxonomyTermController::class, 'move'])->middleware('permission:content.update');
        Route::delete('/taxonomies/terms/{id}', [TaxonomyTermController::class, 'destroy'])->middleware('permission:content.delete');
        Route::post('/taxonomies/terms/reorder', [TaxonomyTermController::class, 'reorder'])->middleware('permission:content.update');

        // Content Taxonomy Assignment API
        Route::post('/content/{id}/terms', [ContentTaxonomyController::class, 'assign']);
        Route::put('/content/{id}/terms', [ContentTaxonomyController::class, 'sync']);
        Route::delete('/content/{id}/terms/{termId}', [ContentTaxonomyController::class, 'remove']);
        Route::post('/content/{id}/auto-categorize', [ContentTaxonomyController::class, 'autoCategorize']);

        // Roles API (list requires roles.read or roles.manage, create/edit/delete requires roles.manage)
        Route::get('/roles', [RoleController::class, 'index']);
        Route::middleware('permission:roles.manage')->group(function () {
            Route::post('/roles', [RoleController::class, 'store']);
            Route::put('/roles/{role}', [RoleController::class, 'update']);
            Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
        });

        // Permissions API (requires roles.manage)
        Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:roles.manage');

        // Webhooks — management CRUD + delivery log (rate-limited: 60/min overall, 10/min on redeliver)
        Route::middleware(['throttle:60,1', 'permission:webhooks.manage'])->group(function () {
            Route::get('/webhooks', [WebhookController::class, 'index']);
            Route::post('/webhooks', [WebhookController::class, 'store']);
            Route::get('/webhooks/{id}', [WebhookController::class, 'show']);
            Route::put('/webhooks/{id}', [WebhookController::class, 'update']);
            Route::delete('/webhooks/{id}', [WebhookController::class, 'destroy']);
            Route::post('/webhooks/{id}/rotate-secret', [WebhookController::class, 'rotateSecret']);
            Route::get('/webhooks/{id}/deliveries', [WebhookDeliveryController::class, 'index']);
            Route::get('/webhooks/{id}/deliveries/{deliveryId}', [WebhookDeliveryController::class, 'show']);
            Route::post('/webhooks/{id}/deliveries/{deliveryId}/redeliver', [WebhookDeliveryController::class, 'redeliver'])
                ->middleware('throttle:10,1');
        });

        // Audit logs (requires audit.view permission)
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');

        // User roles (requires users.roles.assign or roles.manage)
        Route::post('/users/{user}/roles', [UserRoleController::class, 'assignRole'])->middleware('permission:users.roles.assign');
        Route::delete('/users/{user}/roles/{role}', [UserRoleController::class, 'revokeRole'])->middleware('permission:users.roles.assign');
        Route::get('/users/{user}/roles', [UserRoleController::class, 'userRoles']);
        Route::get('/roles/{role}/users', [UserRoleController::class, 'roleUsers'])->middleware('permission:roles.manage');

        // Roles API (list requires roles.read or roles.manage, create/edit/delete requires roles.manage)
        Route::get('/roles', [RoleController::class, 'index']);
        Route::middleware('permission:roles.manage')->group(function () {
            Route::post('/roles', [RoleController::class, 'store']);
            Route::put('/roles/{role}', [RoleController::class, 'update']);
            Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
        });

        // Taxonomies API
        Route::get('/taxonomies', [TaxonomyController::class, 'index']);
        Route::post('/taxonomies', [TaxonomyController::class, 'store'])->middleware('permission:content.create');
        Route::get('/taxonomies/{vocabSlug}', [TaxonomyController::class, 'show']);
        Route::put('/taxonomies/{id}', [TaxonomyController::class, 'update'])->middleware('permission:content.update');
        Route::delete('/taxonomies/{id}', [TaxonomyController::class, 'destroy'])->middleware('permission:content.delete');

        // Taxonomy Terms API
        Route::get('/taxonomies/{vocabSlug}/terms', [TaxonomyTermController::class, 'index']);
        Route::post('/taxonomies/{vocabId}/terms', [TaxonomyTermController::class, 'store'])->middleware('permission:content.create');
        Route::get('/taxonomies/{vocabSlug}/terms/{termSlug}', [TaxonomyTermController::class, 'show']);
        Route::put('/taxonomies/terms/{id}', [TaxonomyTermController::class, 'update'])->middleware('permission:content.update');
        Route::post('/taxonomies/terms/{id}/move', [TaxonomyTermController::class, 'move'])->middleware('permission:content.update');
        Route::delete('/taxonomies/terms/{id}', [TaxonomyTermController::class, 'destroy'])->middleware('permission:content.delete');
        Route::post('/taxonomies/terms/reorder', [TaxonomyTermController::class, 'reorder'])->middleware('permission:content.update');

        // Content Taxonomy Assignment API
        Route::post('/content/{id}/terms', [ContentTaxonomyController::class, 'assign']);
        Route::put('/content/{id}/terms', [ContentTaxonomyController::class, 'sync']);
        Route::delete('/content/{id}/terms/{termId}', [ContentTaxonomyController::class, 'remove']);
        Route::post('/content/{id}/auto-categorize', [ContentTaxonomyController::class, 'autoCategorize']);

        // Versioning
        Route::prefix('/content/{content}/versions')->group(function () {
            Route::get('/', [VersionController::class, 'index']);
            Route::get('/{version}', [VersionController::class, 'show']);
            Route::post('/draft', [VersionController::class, 'createDraft']);
            Route::patch('/{version}', [VersionController::class, 'update']);
            Route::post('/{version}/publish', [VersionController::class, 'publish']);
            Route::post('/{version}/schedule', [VersionController::class, 'schedule']);
            Route::delete('/{version}/schedule', [VersionController::class, 'cancelSchedule']);
            Route::post('/{version}/label', [VersionController::class, 'label']);
            Route::post('/{version}/rollback', [VersionController::class, 'rollback']);
            Route::post('/{version}/branch', [VersionController::class, 'branch']);
        });
        // Create new content
        Route::post('/content', [ContentController::class, 'store'])->middleware('permission:content.create');

        Route::post('/content/{content}/autosave', [AutoSaveController::class, 'save']);
        Route::get('/content/{content}/autosave', [AutoSaveController::class, 'show']);
        Route::delete('/content/{content}/autosave', [AutoSaveController::class, 'discard']);
        Route::get('/content/{content}/diff', [DiffController::class, 'compare']);

        // Personas
        Route::get('/personas', function () {
            return response()->json(['data' => \App\Models\Persona::where('is_active', true)->get()]);
        });

        // Admin search management (requires authentication + search.admin permission)
        Route::prefix('admin/search')->middleware(['auth:sanctum', 'permission:search.admin'])->group(function () {
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
            Route::get('/analytics', [SearchAdminController::class, 'analytics']);
            Route::get('/content-gaps', [SearchAdminController::class, 'contentGaps']);
        });

        // Media Library — CRUD for assets, folders, collections, editing
        Route::prefix('/media')->group(function () {
            // Asset listing, upload, fetch, update, delete
            Route::get('/', [MediaController::class, 'index']);
            Route::post('/', [MediaController::class, 'store']);
            Route::get('/{asset}', [MediaController::class, 'show']);
            Route::patch('/{asset}', [MediaController::class, 'update']);
            Route::delete('/{asset}', [MediaController::class, 'destroy']);
            Route::patch('/{asset}/move', [MediaController::class, 'move']);

            // Image editing (crop, rotate, resize)
            Route::post('/{asset}/edit', [MediaEditController::class, 'edit']);

            // Folders — create, list, update hierarchy
            Route::get('/folders', [MediaFolderController::class, 'index']);
            Route::post('/folders', [MediaFolderController::class, 'store']);
            Route::get('/folders/{folder}', [MediaFolderController::class, 'show']);
            Route::patch('/folders/{folder}', [MediaFolderController::class, 'update']);
            Route::delete('/folders/{folder}', [MediaFolderController::class, 'destroy']);

            // Collections — curated asset groupings
            Route::get('/collections', [MediaCollectionController::class, 'index']);
            Route::post('/collections', [MediaCollectionController::class, 'store']);
            Route::get('/collections/{collection}', [MediaCollectionController::class, 'show']);
            Route::patch('/collections/{collection}', [MediaCollectionController::class, 'update']);
            Route::delete('/collections/{collection}', [MediaCollectionController::class, 'destroy']);
            Route::post('/collections/{collection}/items', [MediaCollectionController::class, 'addItems']);
            Route::delete('/collections/{collection}/items/{itemId}', [MediaCollectionController::class, 'removeItem']);
        });

        // Public Media CDN API (no auth required)
        Route::prefix('/media/public')->group(function () {
            Route::get('/{publicUrl}', [PublicMediaController::class, 'serve']);
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

        // Content repurposing
        Route::middleware('throttle:30,1')->group(function () {
            Route::get('/content/{content}/repurposed', [RepurposingController::class, 'index']);
            Route::get('/repurposed/{repurposedContent}', [RepurposingController::class, 'show']);
            Route::get('/spaces/{space}/repurpose/estimate', [RepurposingController::class, 'estimateCost']);
        });
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/content/{content}/repurpose', [RepurposingController::class, 'store']);
            Route::post('/spaces/{space}/repurpose/batch', [RepurposingController::class, 'batch']);
        });

        // Format templates
        Route::get('/format-templates', [FormatTemplateController::class, 'index']);
        Route::post('/format-templates', [FormatTemplateController::class, 'store']);
        Route::patch('/format-templates/{template}', [FormatTemplateController::class, 'update']);
        Route::delete('/format-templates/{template}', [FormatTemplateController::class, 'destroy']);
    });

    // Plugin admin API
    Route::prefix('admin/plugins')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [PluginAdminController::class, 'index']);
        Route::get('/{name}', [PluginAdminController::class, 'show']);
        Route::post('/{name}/install', [PluginAdminController::class, 'install']);
        Route::post('/{name}/activate', [PluginAdminController::class, 'activate']);
        Route::post('/{name}/deactivate', [PluginAdminController::class, 'deactivate']);
        Route::post('/{name}/uninstall', [PluginAdminController::class, 'uninstall']);
        Route::patch('/{name}/settings', [PluginAdminController::class, 'updateSettings']);
    });
});
