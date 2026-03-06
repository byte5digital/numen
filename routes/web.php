<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BriefAdminController;
use App\Http\Controllers\Admin\ContentAdminController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PageAdminController;
use App\Http\Controllers\Admin\PersonaAdminController;
use App\Http\Controllers\Admin\PipelineAdminController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\QueueMonitorController;
use App\Http\Controllers\Admin\SettingsAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public\BlogController;
use App\Http\Controllers\Public\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Documentation
|--------------------------------------------------------------------------
*/

Route::get('/api/documentation', function () {
    return view('api-docs');
})->name('api.documentation');

Route::get('/api/documentation/spec', function () {
    $spec = file_get_contents(base_path('openapi.yaml'));

    return response($spec, 200)
        ->header('Content-Type', 'application/yaml')
        ->header('Access-Control-Allow-Origin', '*');
})->name('api.documentation.spec');

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Admin Routes (Auth Required)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/content', [ContentAdminController::class, 'index'])->name('admin.content');
    Route::get('/content/{id}', [ContentAdminController::class, 'show'])->name('admin.content.show');
    Route::delete('/content/{id}', [ContentAdminController::class, 'destroy'])->name('admin.content.destroy');
    Route::patch('/content/{id}/status', [ContentAdminController::class, 'updateStatus'])->name('admin.content.status');
    Route::post('/content/{id}/generate-image', [ContentAdminController::class, 'generateImage'])->name('admin.content.generate-image');
    Route::post('/content/{id}/update-brief', [ContentAdminController::class, 'createUpdateBrief'])->name('admin.content.update-brief');
    // Content block editor
    Route::post('/content/{id}/blocks', [ContentAdminController::class, 'addBlock'])->name('admin.content.blocks.store');
    Route::put('/content/{id}/blocks/{blockId}', [ContentAdminController::class, 'updateBlock'])->name('admin.content.blocks.update');
    Route::delete('/content/{id}/blocks/{blockId}', [ContentAdminController::class, 'deleteBlock'])->name('admin.content.blocks.destroy');
    Route::post('/content/{id}/blocks/reorder', [ContentAdminController::class, 'reorderBlocks'])->name('admin.content.blocks.reorder');
    Route::get('/briefs', [BriefAdminController::class, 'index'])->name('admin.briefs');
    Route::get('/briefs/create', [BriefAdminController::class, 'create'])->name('admin.briefs.create');
    Route::post('/briefs', [BriefAdminController::class, 'store'])->name('admin.briefs.store');
    Route::get('/briefs/{id}', [BriefAdminController::class, 'show'])->name('admin.briefs.show');
    Route::post('/briefs/{id}/reprocess', [BriefAdminController::class, 'reprocess'])->name('admin.briefs.reprocess');
    Route::get('/pipelines', [PipelineAdminController::class, 'index'])->name('admin.pipelines');
    Route::post('/pipeline-runs/{id}/approve', [PipelineAdminController::class, 'approveRun'])->name('admin.pipeline-runs.approve');
    Route::post('/pipeline-runs/{id}/reject', [PipelineAdminController::class, 'rejectRun'])->name('admin.pipeline-runs.reject');
    Route::get('/personas', [PersonaAdminController::class, 'index'])->name('admin.personas');
    Route::patch('/personas/{id}', [PersonaAdminController::class, 'update'])->name('admin.personas.update');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('admin.analytics');

    // Settings
    Route::get('/settings', [SettingsAdminController::class, 'index'])->name('admin.settings');
    Route::post('/settings/providers', [SettingsAdminController::class, 'updateProviders'])->name('admin.settings.providers');
    Route::post('/settings/models', [SettingsAdminController::class, 'updateModels'])->name('admin.settings.models');
    Route::post('/settings/costs', [SettingsAdminController::class, 'updateCosts'])->name('admin.settings.costs');

    // Queue Monitor
    Route::get('/queue', [QueueMonitorController::class, 'index'])->name('admin.queue');
    Route::post('/queue/retry/{id}', [QueueMonitorController::class, 'retryFailed'])->name('admin.queue.retry');
    Route::post('/queue/flush', [QueueMonitorController::class, 'flushFailed'])->name('admin.queue.flush');

    // Users
    Route::get('/users', [UserAdminController::class, 'index'])->name('admin.users.index');
    Route::get('/users/create', [UserAdminController::class, 'create'])->name('admin.users.create');
    Route::post('/users', [UserAdminController::class, 'store'])->name('admin.users.store');
    Route::get('/users/{user}/edit', [UserAdminController::class, 'edit'])->name('admin.users.edit');
    Route::put('/users/{user}', [UserAdminController::class, 'update'])->name('admin.users.update');
    Route::delete('/users/{user}', [UserAdminController::class, 'destroy'])->name('admin.users.destroy');

    // Profile / Password
    Route::get('/profile/password', [ProfileController::class, 'editPassword'])->name('admin.profile.password');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('admin.profile.password.update');

    // Pages
    Route::get('/pages', [PageAdminController::class, 'index'])->name('admin.pages');
    Route::get('/pages/{id}/edit', [PageAdminController::class, 'edit'])->name('admin.pages.edit');
    Route::put('/pages/{id}/components/{componentId}', [PageAdminController::class, 'updateComponent'])->name('admin.pages.components.update');
    Route::post('/pages/{id}/components', [PageAdminController::class, 'addComponent'])->name('admin.pages.components.store');
    Route::delete('/pages/{id}/components/{componentId}', [PageAdminController::class, 'deleteComponent'])->name('admin.pages.components.destroy');
    Route::post('/pages/{id}/components/reorder', [PageAdminController::class, 'reorderComponents'])->name('admin.pages.components.reorder');
    Route::post('/pages/{id}/components/{componentId}/generate', [PageAdminController::class, 'generateComponent'])->name('admin.pages.components.generate');
});
