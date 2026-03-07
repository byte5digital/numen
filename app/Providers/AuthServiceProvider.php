<?php

namespace App\Providers;

use App\Models\Content;
use App\Models\ContentPipeline;
use App\Models\PipelineRun;
use App\Models\Space;
use App\Policies\ContentPolicy;
use App\Policies\PipelinePolicy;
use App\Policies\SpacePolicy;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Gate callbacks and policy classes for Numen's RBAC system.
 *
 * The Gate::before callback delegates to AuthorizationService, which means
 * standard Laravel can(), @can, and $this->authorize() calls all route
 * through our RBAC engine automatically.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services (IoC bindings for authorization layer).
     */
    public function register(): void
    {
        $this->app->singleton(AuthorizationService::class);
    }

    /**
     * Boot authorization: register Gate callback and policies.
     */
    public function boot(): void
    {
        // Gate::before delegates all permission checks to AuthorizationService.
        // Returning null (instead of false) means we fall through to other gates
        // for abilities not in our permission string taxonomy (e.g. policy classes).
        Gate::before(function (\App\Models\User $user, string $ability) {
            /** @var AuthorizationService $authz */
            $authz = app(AuthorizationService::class);
            $space = request()->attributes->get('active_space');

            return $authz->can($user, $ability, $space) ?: null;
        });

        // Register resource policies
        Gate::policy(Content::class, ContentPolicy::class);
        Gate::policy(Space::class, SpacePolicy::class);
        Gate::policy(ContentPipeline::class, PipelinePolicy::class);
        Gate::policy(PipelineRun::class, PipelinePolicy::class);
    }
}
