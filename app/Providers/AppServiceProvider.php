<?php

namespace App\Providers;

use App\Agents\AgentFactory;
use App\Models\Setting;
use App\Services\AI\CostTracker;
use App\Services\AI\ImageManager;
use App\Services\AI\ImageProviders\FalImageProvider;
use App\Services\AI\ImageProviders\OpenAIImageProvider;
use App\Services\AI\ImageProviders\ReplicateImageProvider;
use App\Services\AI\ImageProviders\TogetherImageProvider;
use App\Services\AI\LLMManager;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\AzureOpenAIProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
    }

    public function boot(): void
    {
        // Load DB settings into config (overrides .env defaults)
        Setting::loadIntoConfig();
    }
}
