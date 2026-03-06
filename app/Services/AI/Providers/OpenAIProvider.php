<?php

namespace App\Services\AI\Providers;

use App\Models\AIGenerationLog;
use App\Services\AI\Contracts\LLMProvider;
use App\Services\AI\CostTracker;
use App\Services\AI\Exceptions\ProviderRateLimitException;
use App\Services\AI\Exceptions\ProviderUnavailableException;
use App\Services\AI\LLMResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements LLMProvider
{
    protected string $providerName = 'openai';

    public function __construct(protected CostTracker $costTracker) {}

    protected function apiKey(): string
    {
        return (string) config('numen.providers.openai.api_key', '');
    }

    protected function baseUrl(): string
    {
        return (string) config('numen.providers.openai.base_url', 'https://api.openai.com/v1');
    }

    public function getName(): string
    {
        return $this->providerName;
    }

    public function isAvailable(string $model): bool
    {
        if (empty($this->apiKey())) return false;

        $retryAfter = Cache::get("llm:rate:{$this->getName()}:{$model}:retry_after");
        return !($retryAfter && $retryAfter > now()->timestamp);
    }

    public function complete(array $params): LLMResponse
    {
        $model   = $params['model'] ?? config('numen.providers.openai.default_model', 'gpt-4o');
        $purpose = $params['_purpose'] ?? 'unknown';
        $start   = microtime(true);

        // OpenAI format: system prompt goes as first message with role=system
        $messages = [];
        if (!empty($params['system'])) {
            $messages[] = ['role' => 'system', 'content' => $params['system']];
        }
        $messages = array_merge($messages, $params['messages'] ?? []);

        // Newer OpenAI models (gpt-4o, o1, o3, gpt-5) use max_completion_tokens
        $maxTokensKey = $this->usesMaxCompletionTokens($model) ? 'max_completion_tokens' : 'max_tokens';

        $apiParams = [
            'model'        => $model,
            $maxTokensKey  => $params['max_tokens'] ?? 4096,
            'temperature'  => $params['temperature'] ?? 0.7,
            'messages'     => $messages,
        ];

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout((int) config("numen.providers.{$this->providerName}.timeout", 120))
                ->post("{$this->baseUrl()}/chat/completions", $apiParams);

        } catch (\Exception $e) {
            throw new ProviderUnavailableException($this->getName(), $model, $e->getMessage(), $e);
        }

        // OpenAI/Azure also return 400 for quota exhaustion
        if ($response->status() === 400 || $response->status() === 429) {
            $errorMsg = $response->json('error.message', '');
            if ($response->status() === 429 ||
                str_contains(strtolower($errorMsg), 'quota') ||
                str_contains(strtolower($errorMsg), 'usage limit') ||
                str_contains(strtolower($errorMsg), 'rate limit')) {
                $retryAfter = (int) ($response->header('retry-after') ?: ($response->status() === 400 ? 3600 : 60));
                Cache::put(
                    "llm:rate:{$this->getName()}:{$model}:retry_after",
                    now()->addSeconds($retryAfter)->timestamp,
                    $retryAfter,
                );
                throw new ProviderRateLimitException($this->getName(), $model, $retryAfter);
            }
        }

        if ($response->status() >= 500) {
            throw new ProviderUnavailableException(
                $this->getName(), $model,
                "HTTP {$response->status()}: {$response->body()}",
            );
        }

        if ($response->failed()) {
            throw new ProviderUnavailableException(
                $this->getName(), $model,
                "HTTP {$response->status()}: " . $response->json('error.message', $response->body()),
            );
        }

        $data         = $response->json();
        $latencyMs    = (int) ((microtime(true) - $start) * 1000);
        $inputTokens  = $data['usage']['prompt_tokens'] ?? 0;
        $outputTokens = $data['usage']['completion_tokens'] ?? 0;
        $content      = $data['choices'][0]['message']['content'] ?? '';
        $stopReason   = $data['choices'][0]['finish_reason'] ?? 'stop';
        $cost         = $this->costTracker->calculateCost($model, $inputTokens, $outputTokens);

        $this->log($params, $content, $model, $purpose, $inputTokens, $outputTokens, $cost, $latencyMs, $data);

        return new LLMResponse(
            content:      $content,
            model:        $model,
            provider:     $this->getName(),
            inputTokens:  $inputTokens,
            outputTokens: $outputTokens,
            costUsd:      $cost,
            latencyMs:    $latencyMs,
            stopReason:   $stopReason,
            raw:          $data,
        );
    }

    /**
     * Newer OpenAI models require max_completion_tokens instead of max_tokens.
     */
    protected function usesMaxCompletionTokens(string $model): bool
    {
        // o1, o3, gpt-4o (2024+), gpt-5 all use the new param
        return str_starts_with($model, 'o1')
            || str_starts_with($model, 'o3')
            || str_starts_with($model, 'gpt-5')
            || str_starts_with($model, 'gpt-4o');
    }

    protected function headers(): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey()}",
            'Content-Type'  => 'application/json',
        ];
    }

    protected function log(
        array $params, string $content, string $model, string $purpose,
        int $inputTokens, int $outputTokens, float $cost, int $latencyMs, array $raw,
    ): void {
        AIGenerationLog::create([
            'pipeline_run_id' => $params['_pipeline_run_id'] ?? null,
            'persona_id'      => $params['_persona_id'] ?? null,
            'model'           => $model,
            'purpose'         => $purpose,
            'messages'        => $params['messages'] ?? [],
            'response'        => $content,
            'input_tokens'    => $inputTokens,
            'output_tokens'   => $outputTokens,
            'cost_usd'        => $cost,
            'latency_ms'      => $latencyMs,
            'stop_reason'     => $raw['choices'][0]['finish_reason'] ?? null,
            'metadata'        => [
                'provider'    => $this->getName(),
                'temperature' => $params['temperature'] ?? null,
            ],
        ]);
    }
}
