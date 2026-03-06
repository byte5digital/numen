<?php

namespace App\Providers;

use App\Agents\AgentFactory;
use App\Models\Setting;
use App\Services\AI\CostTracker;
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
