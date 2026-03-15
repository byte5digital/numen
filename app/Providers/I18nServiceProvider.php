<?php

namespace App\Providers;

use App\Console\Commands\SetupI18nCommand;
use App\Services\AITranslationService;
use App\Services\LocaleService;
use App\Services\TranslationService;
use Illuminate\Support\ServiceProvider;

class I18nServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LocaleService::class);
        $this->app->singleton(TranslationService::class);
        $this->app->singleton(AITranslationService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SetupI18nCommand::class]);
        }
    }
}
