<?php

namespace App\Providers;

use App\Agents\AgentFactory;
use App\Events\Content\ContentPublished;
use App\Events\Content\ContentUnpublished;
use App\Listeners\IndexContentForSearch;
use App\Listeners\RemoveFromSearchIndex;
use App\Models\Content;
use App\Models\Setting;
use App\Policies\ContentPolicy;
use App\Services\AI\CostTracker;
use App\Services\Authorization\PermissionRegistrar;
use App\Services\AuthorizationService;
use App\Services\AI\ImageManager;
use App\Services\AI\ImageProviders\FalImageProvider;
use App\Services\AI\ImageProviders\OpenAIImageProvider;
use App\Services\AI\ImageProviders\ReplicateImageProvider;
use App\Services\AI\ImageProviders\TogetherImageProvider;
use App\Services\AI\LLMManager;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\AzureOpenAIProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\Search\ConversationalDriver;
use App\Services\Search\EmbeddingService;
use App\Services\Search\InstantSearchDriver;
use App\Services\Search\PromotedResultsService;
use App\Services\Search\SearchAnalyticsRecorder;
use App\Services\Search\SearchCapabilityDetector;
use App\Services\Search\SearchRanker;
use App\Services\Search\SearchService;
use App\Services\Search\SemanticSearchDriver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Authorization ──────────────────────────────────────────────────
        $this->app->singleton(AuthorizationService::class);
        $this->app->singleton(PermissionRegistrar::class);

        // ── New multi-provider AI layer ────────────────────────────────────
        $this->app->singleton(CostTracker::class);

        $this->app->singleton(AnthropicProvider::class, fn ($app) => new AnthropicProvider($app->make(CostTracker::class))
        );
        $this->app->singleton(OpenAIProvider::class, fn ($app) => new OpenAIProvider($app->make(CostTracker::class))
        );
        $this->app->singleton(AzureOpenAIProvider::class, fn ($app) => new AzureOpenAIProvider($app->make(CostTracker::class))
        );

        $this->app->singleton(LLMManager::class, fn ($app) => new LLMManager(
            $app->make(AnthropicProvider::class),
            $app->make(OpenAIProvider::class),
            $app->make(AzureOpenAIProvider::class),
            $app->make(CostTracker::class),
        ));

        // ── Multi-provider Image Generation layer ──────────────────────────
        $this->app->singleton(OpenAIImageProvider::class);
        $this->app->singleton(TogetherImageProvider::class);
        $this->app->singleton(FalImageProvider::class);
        $this->app->singleton(ReplicateImageProvider::class);

        $this->app->singleton(ImageManager::class, fn ($app) => new ImageManager(
            $app->make(OpenAIImageProvider::class),
            $app->make(TogetherImageProvider::class),
            $app->make(FalImageProvider::class),
            $app->make(ReplicateImageProvider::class),
            $app->make(CostTracker::class),
        ));

        // ── AgentFactory now routes through LLMManager ─────────────────────
        $this->app->singleton(AgentFactory::class, fn ($app) => new AgentFactory($app->make(LLMManager::class))
        );

        // ── Search layer ───────────────────────────────────────────────────
        $this->app->singleton(EmbeddingService::class, fn ($app) => new EmbeddingService(
            $app->make(CostTracker::class),
        ));

        $this->app->singleton(SemanticSearchDriver::class, fn ($app) => new SemanticSearchDriver(
            $app->make(EmbeddingService::class),
        ));

        $this->app->singleton(ConversationalDriver::class, fn ($app) => new ConversationalDriver(
            $app->make(SemanticSearchDriver::class),
            $app->make(EmbeddingService::class),
            $app->make(LLMManager::class),
        ));

        $this->app->singleton(SearchService::class, fn ($app) => new SearchService(
            $app->make(InstantSearchDriver::class),
            $app->make(SemanticSearchDriver::class),
            $app->make(ConversationalDriver::class),
            $app->make(SearchRanker::class),
            $app->make(PromotedResultsService::class),
            $app->make(SearchAnalyticsRecorder::class),
            $app->make(SearchCapabilityDetector::class),
        ));
    }

    public function boot(): void
    {
        // Register content access policies
        Gate::policy(Content::class, ContentPolicy::class);

        // Load DB settings into config (overrides .env defaults)
        Setting::loadIntoConfig();

        // Register search event listeners
        Event::listen(ContentPublished::class, IndexContentForSearch::class);
        Event::listen(ContentUnpublished::class, RemoveFromSearchIndex::class);
    }
}
