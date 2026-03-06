<?php

namespace App\Services\AI;

use App\Models\Persona;
use App\Services\AI\Contracts\LLMProvider;
use App\Services\AI\Exceptions\AllProvidersFailedException;
use App\Services\AI\Exceptions\CostLimitExceededException;
use App\Services\AI\Exceptions\ProviderRateLimitException;
use App\Services\AI\Exceptions\ProviderUnavailableException;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\AzureOpenAIProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Support\Facades\Log;

/**
 * Central LLM gateway with multi-provider routing and automatic fallback.
 *
 * Model resolution priority:
 * 1. Explicit provider prefix: "anthropic:claude-sonnet-4-6", "openai:gpt-4o", "azure:gpt-4o"
 * 2. Auto-detection by model name pattern
 * 3. Default provider from config
 *
 * Fallback chain (configurable in numen.fallback_chain):
 * When a provider returns 429 or 5xx, the next provider in the chain is tried
 * with the closest equivalent model.
 */
class LLMManager
{
    /** @var array<string, LLMProvider> */
    private array $providers;

    /** @var array<string, string> Model aliases for cross-provider fallback */
    private array $equivalents = [
        // Anthropic → OpenAI equivalents
        'claude-opus-4-6' => 'gpt-4o',
        'claude-sonnet-4-6' => 'gpt-4o',
        'claude-haiku-4-5-20251001' => 'gpt-4o-mini',
        // OpenAI → Anthropic equivalents
        'gpt-4o' => 'claude-sonnet-4-6',
        'gpt-4o-mini' => 'claude-haiku-4-5-20251001',
        'gpt-4-turbo' => 'claude-opus-4-6',
    ];

    public function __construct(
        AnthropicProvider $anthropic,
        OpenAIProvider $openai,
        AzureOpenAIProvider $azure,
        private CostTracker $costTracker,
    ) {
        $this->providers = [
            'anthropic' => $anthropic,
            'openai' => $openai,
            'azure' => $azure,
        ];
    }

    /**
     * Complete a message with automatic provider routing and fallback.
     *
     * @param  array  $params  Normalized params:
     *                         model       string  — "claude-sonnet-4-6" | "anthropic:claude-sonnet-4-6" | "gpt-4o"
     *                         system      string
     *                         messages    array
     *                         max_tokens  int
     *                         temperature float
     *                         _purpose    string
     *                         _pipeline_run_id  string|null
     *                         _persona_id       string|null
     */
    public function complete(array $params, ?string $pipelineRunId = null, ?Persona $persona = null): LLMResponse
    {
        // Inject internal context fields
        if ($pipelineRunId) {
            $params['_pipeline_run_id'] = $pipelineRunId;
        }
        if ($persona) {
            $params['_persona_id'] = $persona->id;
        }

        [$providerName, $model] = $this->resolveProvider($params['model'] ?? '');
        $params['model'] = $model;

        $fallbackChain = $this->resolveChain($providerName, $model, $persona?->getFallbackFullModel());
        $attempts = [];

        // Pre-flight cost check: block before making any API call
        if (! $this->costTracker->isWithinLimits()) {
            throw new CostLimitExceededException(
                'AI cost limit already exceeded. Generation blocked.',
                period: 'pre-flight',
            );
        }

        foreach ($fallbackChain as [$currentProvider, $currentModel]) {
            $provider = $this->providers[$currentProvider] ?? null;

            if (! $provider) {
                continue;
            }
            if (! $provider->isAvailable($currentModel)) {
                $attempts[] = ['provider' => $currentProvider, 'error' => 'not available (rate limited or no key)'];

                continue;
            }

            try {
                $callParams = array_merge($params, ['model' => $currentModel]);
                $response = $provider->complete($callParams);

                // Record cost and enforce limits post-call
                $withinLimits = $this->costTracker->recordUsage($response->costUsd);
                if (! $withinLimits) {
                    Log::warning('AI cost limit exceeded after API call', [
                        'cost_usd' => $response->costUsd,
                        'provider' => $currentProvider,
                        'model' => $currentModel,
                    ]);
                    // Allow this response through (already paid for) but subsequent calls will be blocked
                }

                // Log if we had to fall back
                if ($currentProvider !== $providerName || $currentModel !== $model) {
                    Log::info('LLM fallback used', [
                        'original_provider' => $providerName,
                        'original_model' => $model,
                        'used_provider' => $currentProvider,
                        'used_model' => $currentModel,
                    ]);
                }

                return $response;

            } catch (ProviderRateLimitException|ProviderUnavailableException $e) {
                Log::warning('LLM provider failed, trying next fallback', [
                    'provider' => $currentProvider,
                    'model' => $currentModel,
                    'error' => $e->getMessage(),
                ]);
                $attempts[] = ['provider' => $currentProvider, 'error' => $e->getMessage()];

                continue;
            } catch (\Exception $e) {
                // Catch any unexpected error (e.g. malformed response, network timeout)
                // so the fallback chain still runs instead of crashing the pipeline.
                Log::warning('LLM provider unexpected exception, trying next fallback', [
                    'provider' => $currentProvider,
                    'model' => $currentModel,
                    'exception' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
                $attempts[] = ['provider' => $currentProvider, 'error' => get_class($e).': '.$e->getMessage()];

                continue;
            }
        }

        throw new AllProvidersFailedException($attempts);
    }

    /**
     * Legacy-compatible wrapper matching the old AnthropicClient::createMessage() signature.
     * Allows existing agents to work with zero changes during transition.
     */
    public function createMessage(array $params, ?string $pipelineRunId = null, ?Persona $persona = null): array
    {
        $response = $this->complete($params, $pipelineRunId, $persona);

        // Return in Anthropic-style format for backwards compat
        return [
            'content' => [['type' => 'text', 'text' => $response->content]],
            'model' => $response->model,
            'stop_reason' => $response->stopReason,
            'usage' => [
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
            ],
            '_provider' => $response->provider,
            '_cost_usd' => $response->costUsd,
        ];
    }

    /**
     * Legacy-compatible extract text helper.
     */
    public function extractTextContent(array $response): string
    {
        return collect($response['content'] ?? [])->where('type', 'text')->pluck('text')->implode("\n");
    }

    /**
     * Resolve provider name and model from a model string.
     * Input: "anthropic:claude-sonnet-4-6" | "gpt-4o" | "claude-sonnet-4-6"
     * Returns: ['anthropic', 'claude-sonnet-4-6']
     */
    private function resolveProvider(string $modelStr): array
    {
        // Explicit provider prefix
        if (str_contains($modelStr, ':')) {
            [$provider, $model] = explode(':', $modelStr, 2);

            return [$provider, $model];
        }

        // Auto-detect by model name
        if (str_starts_with($modelStr, 'claude')) {
            return ['anthropic', $modelStr];
        }
        if (str_starts_with($modelStr, 'gpt') || str_starts_with($modelStr, 'o1') || str_starts_with($modelStr, 'o3')) {
            return ['openai', $modelStr];
        }

        // Default provider from config
        $default = config('numen.default_provider', 'anthropic');
        $model = $modelStr ?: config("numen.providers.{$default}.default_model", 'claude-sonnet-4-6');

        return [$default, $model];
    }

    private function defaultModelFor(string $provider): string
    {
        return match ($provider) {
            'anthropic' => config('numen.providers.anthropic.default_model', 'claude-sonnet-4-6'),
            'openai' => config('numen.providers.openai.default_model', 'gpt-4o'),
            'azure' => config('numen.providers.azure.default_model', 'gpt-4o'),
            default => 'gpt-4o',
        };
    }

    /**
     * Actually build the full chain with [provider, model] pairs.
     * Replaces the stub in ::complete() above.
     */
    private function resolveChain(string $primaryProvider, string $primaryModel, ?string $personaFallback = null): array
    {
        $configChain = config('numen.fallback_chain', ['anthropic', 'openai', 'azure']);

        $ordered = collect(array_merge(
            [$primaryProvider],
            array_filter($configChain, fn ($p) => $p !== $primaryProvider),
        ));

        return $ordered->map(function ($provider) use ($primaryProvider, $primaryModel, $personaFallback) {
            if ($provider === $primaryProvider) {
                return [$provider, $primaryModel];
            }

            // Persona-specific fallback overrides the generic equivalents map
            if ($personaFallback) {
                [$fbProvider, $fbModel] = $this->resolveProvider($personaFallback);
                if ($fbProvider === $provider) {
                    return [$provider, $fbModel];
                }
            }

            $equivalent = $this->equivalents[$primaryModel] ?? $this->defaultModelFor($provider);

            return [$provider, $equivalent];
        })->all();
    }
}
