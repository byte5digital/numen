<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BriefController;
use App\Http\Controllers\Api\ComponentDefinitionController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\ContentTaxonomyController;
use App\Http\Controllers\Api\LocaleController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Api\TaxonomyTermController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\Api\UserRoleController;
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

        // Locales (read-only, public)
        Route::get('/locales', [LocaleController::class, 'index']);
        Route::get('/locales/{code}', [LocaleController::class, 'show']);
    });

    // Component type definitions (public read, authenticated write) — tighter limit
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/component-definitions', [ComponentDefinitionController::class, 'index']);
        Route::get('/component-definitions/{id}', [ComponentDefinitionController::class, 'show']);
    });

    // Taxonomy (public read) — master data, infrequent changes
    Route::middleware('throttle:20,1')->group(function () {
        Route::get('/taxonomies', [TaxonomyController::class, 'index']);
        Route::get('/taxonomies/{id}', [TaxonomyController::class, 'show']);
        Route::get('/taxonomies/{id}/terms', [TaxonomyTermController::class, 'index']);
        Route::get('/taxonomy-terms/{id}', [TaxonomyTermController::class, 'show']);
    });

    // Authenticated Management Routes
    Route::middleware(['auth:sanctum', 'throttle:100,1'])->group(function () {
        // Content management (all methods)
        Route::apiResource('content', ContentController::class);
        Route::post('/content/{id}/publish', [ContentController::class, 'publish']);
        Route::post('/content/{id}/unpublish', [ContentController::class, 'unpublish']);
        Route::post('/content/{id}/translate', [TranslationController::class, 'create']);

        // Pages management
        Route::apiResource('pages', PageController::class);

        // Locales management
        Route::apiResource('locales', LocaleController::class);
        Route::post('/locales/{code}/set-default', [LocaleController::class, 'setDefault']);
        Route::post('/locales/{code}/disable', [LocaleController::class, 'disable']);

        // Translations management
        Route::apiResource('translations', TranslationController::class, ['only' => ['index', 'show', 'destroy']]);
        Route::get('/translations/matrix/{contentId}', [TranslationController::class, 'matrix']);
        Route::post('/translations/{id}/approve', [TranslationController::class, 'approve']);
        Route::post('/translations/{id}/reject', [TranslationController::class, 'reject']);
        Route::post('/translations/batch-approve', [TranslationController::class, 'batchApprove']);

        // Taxonomy management
        Route::apiResource('taxonomies', TaxonomyController::class);
        Route::apiResource('taxonomy-terms', TaxonomyTermController::class);
        Route::post('/taxonomies/{id}/reorder', [TaxonomyController::class, 'reorder']);

        // Briefs management
        Route::apiResource('briefs', BriefController::class);
        Route::post('/briefs/{id}/lock', [BriefController::class, 'lock']);
        Route::post('/briefs/{id}/unlock', [BriefController::class, 'unlock']);

        // Roles & Users
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('user-roles', UserRoleController::class);

        // Webhooks
        Route::apiResource('webhooks', WebhookController::class);
        Route::get('/webhooks/{id}/deliveries', [WebhookDeliveryController::class, 'index']);
        Route::get('/webhooks/{id}/deliveries/{deliveryId}', [WebhookDeliveryController::class, 'show']);
        Route::post('/webhooks/{id}/deliveries/{deliveryId}/retry', [WebhookDeliveryController::class, 'retry']);

        // Content versioning
        Route::get('/content/{id}/versions', [VersionController::class, 'index']);
        Route::get('/content/{id}/versions/{versionId}', [VersionController::class, 'show']);
        Route::post('/content/{id}/versions/{versionId}/restore', [VersionController::class, 'restore']);
        Route::get('/content/{id}/diff/{versionId}', [DiffController::class, 'show']);
        Route::post('/content/{id}/auto-save', [AutoSaveController::class, 'store']);

        // Audit log
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/{id}', [AuditLogController::class, 'show']);
        Route::get('/audit-logs/entity/{entityType}/{entityId}', [AuditLogController::class, 'byEntity']);

        // Content relationships
        Route::post('/content/{id}/taxonomies', [ContentTaxonomyController::class, 'attach']);
        Route::delete('/content/{id}/taxonomies/{taxonomyId}', [ContentTaxonomyController::class, 'detach']);
    });
});
