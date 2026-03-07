<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\PipelineRun;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\AzureOpenAIProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\Anthropic\CostTracker;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(
        CostTracker $costTracker,
        AnthropicProvider $anthropic,
        OpenAIProvider $openai,
        AzureOpenAIProvider $azure,
    ) {
        $defaultProvider = config('numen.default_provider', 'anthropic');
        $fallbackChain = config('numen.fallback_chain', ['anthropic', 'openai', 'azure']);
        $defaultModel = config('numen.models.generation', 'claude-sonnet-4-6');

        $providers = [
            [
                'name' => 'anthropic',
                'label' => 'Anthropic',
                'available' => $anthropic->isAvailable($defaultModel),
                'key_set' => ! empty(config('numen.providers.anthropic.api_key')),
                'default_model' => config('numen.providers.anthropic.default_model', 'claude-sonnet-4-6'),
                'is_default' => $defaultProvider === 'anthropic',
                'in_chain' => in_array('anthropic', $fallbackChain),
            ],
            [
                'name' => 'openai',
                'label' => 'OpenAI',
                'available' => $openai->isAvailable('gpt-5-mini'),
                'key_set' => ! empty(config('numen.providers.openai.api_key')),
                'default_model' => config('numen.providers.openai.default_model', 'gpt-5-mini'),
                'is_default' => $defaultProvider === 'openai',
                'in_chain' => in_array('openai', $fallbackChain),
            ],
            [
                'name' => 'azure',
                'label' => 'Azure AI Foundry',
                'available' => $azure->isAvailable('gpt-5-mini'),
                'key_set' => ! empty(config('numen.providers.azure.api_key')) && ! empty(config('numen.providers.azure.endpoint')),
                'default_model' => config('numen.providers.azure.default_model', 'gpt-5-mini'),
                'is_default' => $defaultProvider === 'azure',
                'in_chain' => in_array('azure', $fallbackChain),
            ],
        ];
        $stats = [
            'published' => Content::where('status', 'published')->count(),
            'in_pipeline' => Content::where('status', 'in_pipeline')->count(),
            'pending_review' => PipelineRun::where('status', 'paused_for_review')->count(),
            'draft' => Content::where('status', 'draft')->count(),
        ];

        $recentContent = Content::with('currentVersion', 'contentType')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->currentVersion->title ?? 'Untitled',
                'type' => $c->contentType->slug,
                'locale' => $c->locale,
                'status' => $c->status,
            ]);

        $recentRuns = PipelineRun::with('brief')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'brief_title' => $r->brief?->title ?? 'Unknown', // @phpstan-ignore nullsafe.neverNull
                'current_stage' => $r->current_stage,
                'status' => $r->status,
            ]);

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentContent' => $recentContent,
            'recentRuns' => $recentRuns,
            'costToday' => $costTracker->getDailySpend(),
            'providers' => $providers,
            'fallbackChain' => $fallbackChain,
        ]);
    }
}
