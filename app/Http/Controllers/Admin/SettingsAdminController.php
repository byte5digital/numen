<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\AzureOpenAIProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class SettingsAdminController extends Controller
{
    /** Available models per provider for the dropdowns */
    private array $availableModels = [
        'anthropic' => [
            'claude-opus-4-6',
            'claude-sonnet-4-6',
            'claude-haiku-4-5-20251001',
        ],
        'openai' => [
            // GPT-5 family (2025)
            'gpt-5.4', 'gpt-5.2', 'gpt-5.1', 'gpt-5', 'gpt-5-mini', 'gpt-5-nano',
            // GPT-4.1 family (2025)
            'gpt-4.1',
            'gpt-4.1-mini',
            'gpt-4.1-nano',
            // o-series reasoning models
            'o4-mini',
            'o3',
            'o1',
            // Legacy (kept for existing deployments)
            'gpt-4o',
            'gpt-4o-mini',
        ],
        'azure' => [
            // GPT-5 family
            'gpt-5.4', 'gpt-5.2', 'gpt-5.1', 'gpt-5', 'gpt-5-mini',
            // GPT-4.1 family
            'gpt-4.1',
            'gpt-4.1-mini',
            // Legacy (kept for existing deployments)
            'gpt-4o',
            'gpt-4o-mini',
        ],
    ];

    public function index(
        AnthropicProvider $anthropic,
        OpenAIProvider $openai,
        AzureOpenAIProvider $azure,
    ) {
        $current = $this->currentValues();

        // Live availability check
        $providerStatus = [
            'anthropic' => [
                'available' => $anthropic->isAvailable($current['ai.providers.anthropic.default_model'] ?? 'claude-sonnet-4-6'),
                'key_set' => ! empty(config('numen.providers.anthropic.api_key')),
            ],
            'openai' => [
                'available' => $openai->isAvailable($current['ai.providers.openai.default_model'] ?? 'gpt-5-mini'),
                'key_set' => ! empty(config('numen.providers.openai.api_key')),
            ],
            'azure' => [
                'available' => $azure->isAvailable($current['ai.providers.azure.default_model'] ?? 'gpt-5-mini'),
                'key_set' => ! empty(config('numen.providers.azure.api_key')) && ! empty(config('numen.providers.azure.endpoint')),
            ],
        ];

        return Inertia::render('Settings/Index', [
            'current' => $current,
            'providerStatus' => $providerStatus,
            'availableModels' => $this->availableModels,
            // Separate flags so the Vue knows a key is set without exposing the value
            'keySet' => [
                'anthropic' => ! empty(config('numen.providers.anthropic.api_key')),
                'openai' => ! empty(config('numen.providers.openai.api_key')),
                'azure' => ! empty(config('numen.providers.azure.api_key')),
            ],
        ]);
    }

    public function updateProviders(Request $request)
    {
        // Inertia sends flat dot-notation JSON keys; undot them so Laravel's
        // validator can traverse nested paths correctly, then re-flatten.
        $nested = Arr::undot($request->all());

        $data = validator($nested, [
            'ai.default_provider' => ['required', 'in:anthropic,openai,azure'],
            'ai.fallback_chain' => ['required', 'string'],

            // Anthropic
            'ai.providers.anthropic.api_key' => ['nullable', 'string'],
            'ai.providers.anthropic.base_url' => ['nullable', 'url'],
            'ai.providers.anthropic.default_model' => ['required', 'string'],

            // OpenAI
            'ai.providers.openai.api_key' => ['nullable', 'string'],
            'ai.providers.openai.base_url' => ['nullable', 'url'],
            'ai.providers.openai.default_model' => ['required', 'string'],

            // Azure
            'ai.providers.azure.api_key' => ['nullable', 'string'],
            'ai.providers.azure.endpoint' => ['nullable', 'url'],
            'ai.providers.azure.api_version' => ['nullable', 'string'],
            'ai.providers.azure.default_model' => ['required', 'string'],
            'ai.providers.azure.deployments.gpt-5-mini' => ['nullable', 'string'],
            'ai.providers.azure.deployments.gpt-5-nano' => ['nullable', 'string'],
        ])->validate();

        // Re-flatten to dot-notation keys for Setting::setMany()
        $flat = Arr::dot($data);

        // Only save API keys when a new value was actually entered (non-empty)
        foreach (['ai.providers.anthropic.api_key', 'ai.providers.openai.api_key', 'ai.providers.azure.api_key'] as $keyField) {
            if (empty($flat[$keyField])) {
                unset($flat[$keyField]);
            }
        }

        Setting::setMany($flat, 'ai_providers');

        Cache::forget('settings:all');
        Setting::loadIntoConfig();

        return back()->with('success', 'Provider settings saved.');
    }

    public function updateModels(Request $request)
    {
        $nested = Arr::undot($request->all());

        $data = validator($nested, [
            'ai.models.generation' => ['required', 'string'],
            'ai.models.generation_premium' => ['required', 'string'],
            'ai.models.seo' => ['required', 'string'],
            'ai.models.review' => ['required', 'string'],
            'ai.models.planning' => ['required', 'string'],
            'ai.models.classification' => ['required', 'string'],
        ])->validate();

        Setting::setMany(Arr::dot($data), 'ai_models');

        Cache::forget('settings:all');
        Setting::loadIntoConfig();

        return back()->with('success', 'Model assignments saved.');
    }

    public function updateCosts(Request $request)
    {
        $nested = Arr::undot($request->all());

        $data = validator($nested, [
            'ai.cost_limits.daily_usd' => ['required', 'numeric', 'min:0'],
            'ai.cost_limits.per_content_usd' => ['required', 'numeric', 'min:0'],
            'ai.cost_limits.monthly_usd' => ['required', 'numeric', 'min:0'],
        ])->validate();

        Setting::setMany(Arr::dot($data), 'cost_limits');

        Cache::forget('settings:all');
        Setting::loadIntoConfig();

        return back()->with('success', 'Cost limits saved.');
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Build current effective values by merging config (from .env) with DB overrides.
     * API keys are masked if set.
     */
    private function currentValues(): array
    {
        return [
            // Routing
            'ai.default_provider' => config('numen.default_provider', 'anthropic'),
            'ai.fallback_chain' => (function () {
                $chain = config('numen.fallback_chain', ['anthropic', 'openai', 'azure']);

                return is_array($chain) ? implode(',', $chain) : (string) $chain;
            })(),

            // Anthropic — API keys always sent as empty string; Vue shows "configured" badge from keySet prop
            'ai.providers.anthropic.api_key' => '',
            'ai.providers.anthropic.base_url' => config('numen.providers.anthropic.base_url', 'https://api.anthropic.com'),
            'ai.providers.anthropic.default_model' => config('numen.providers.anthropic.default_model', 'claude-sonnet-4-6'),

            // OpenAI
            'ai.providers.openai.api_key' => '',
            'ai.providers.openai.base_url' => config('numen.providers.openai.base_url', 'https://api.openai.com/v1'),
            'ai.providers.openai.default_model' => config('numen.providers.openai.default_model', 'gpt-5-mini'),

            // Azure
            'ai.providers.azure.api_key' => '',
            'ai.providers.azure.endpoint' => config('numen.providers.azure.endpoint', ''),
            'ai.providers.azure.api_version' => config('numen.providers.azure.api_version', '2024-02-01'),
            'ai.providers.azure.default_model' => config('numen.providers.azure.default_model', 'gpt-5-mini'),
            'ai.providers.azure.deployments.gpt-5-mini' => config('numen.providers.azure.deployments.gpt-5-mini', 'gpt-5-mini'),
            'ai.providers.azure.deployments.gpt-5-nano' => config('numen.providers.azure.deployments.gpt-5-nano', 'gpt-5-nano'),

            // Model role assignments
            'ai.models.generation' => config('numen.models.generation', 'claude-sonnet-4-6'),
            'ai.models.generation_premium' => config('numen.models.generation_premium', 'claude-opus-4-6'),
            'ai.models.seo' => config('numen.models.seo', 'claude-haiku-4-5-20251001'),
            'ai.models.review' => config('numen.models.review', 'claude-opus-4-6'),
            'ai.models.planning' => config('numen.models.planning', 'claude-opus-4-6'),
            'ai.models.classification' => config('numen.models.classification', 'claude-haiku-4-5-20251001'),

            // Cost limits
            'ai.cost_limits.daily_usd' => config('numen.cost_limits.daily_usd', 50),
            'ai.cost_limits.per_content_usd' => config('numen.cost_limits.per_content_usd', 2),
            'ai.cost_limits.monthly_usd' => config('numen.cost_limits.monthly_usd', 500),
        ];
    }
}
