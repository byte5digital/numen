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

class AnthropicProvider implements LLMProvider
{
    public function __construct(private CostTracker $costTracker) {}

    private function apiKey(): string
    {
        return (string) config('numen.providers.anthropic.api_key', '');
    }

    private function baseUrl(): string
    {
        return (string) config('numen.providers.anthropic.base_url', 'https://api.anthropic.com');
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    public function isAvailable(string $model): bool
    {
        if (empty($this->apiKey())) return false;

        $retryAfter = Cache::get("llm:rate:{$this->getName()}:{$model}:retry_after");
        return !($retryAfter && $retryAfter > now()->timestamp);
    }

    public function complete(array $params): LLMResponse
    {
        $model   = $params['model'] ?? config('numen.providers.anthropic.default_model', 'claude-sonnet-4-6');
        $purpose = $params['_purpose'] ?? 'unknown';
        $start   = microtime(true);

        // Anthropic uses separate 'system' field; messages contain user/assistant only
        $apiParams = [
            'model'       => $model,
            'max_tokens'  => $params['max_tokens'] ?? 4096,
            'temperature' => $params['temperature'] ?? 0.7,
            'system'      => $params['system'] ?? '',
            'messages'    => $params['messages'] ?? [],
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey(),
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
                ->timeout((int) config('numen.providers.anthropic.timeout', 120))
                ->post("{$this->baseUrl()}/v1/messages", $apiParams);

        } catch (\Exception $e) {
            throw new ProviderUnavailableException($this->getName(), $model, $e->getMessage(), $e);
        }

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('retry-after') ?: 60);
            Cache::put(
                "llm:rate:{$this->getName()}:{$model}:retry_after",
                now()->addSeconds($retryAfter)->timestamp,
                $retryAfter,
            );
            throw new ProviderRateLimitException($this->getName(), $model, $retryAfter);
        }

        // Anthropic returns 400 for workspace usage-limit exhaustion (not 429)
        if ($response->status() === 400) {
            $errorMsg = $response->json('error.message', '');
            if (str_contains(strtolower($errorMsg), 'usage limit') ||
                str_contains(strtolower($errorMsg), 'workspace api usage')) {
                // Cache as rate-limited for 1 hour so isAvailable() skips it
                Cache::put(
                    "llm:rate:{$this->getName()}:{$model}:retry_after",
                    now()->addHour()->timestamp,
                    3600,
                );
                throw new ProviderRateLimitException($this->getName(), $model, 3600);
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

        $data        = $response->json();
        $latencyMs   = (int) ((microtime(true) - $start) * 1000);
        $inputTokens = $data['usage']['input_tokens'] ?? 0;
        $outputTokens= $data['usage']['output_tokens'] ?? 0;
        $cacheTokens = $data['usage']['cache_read_input_tokens'] ?? 0;
        $content     = collect($data['content'] ?? [])->where('type', 'text')->pluck('text')->implode("\n");
        $cost        = $this->costTracker->calculateCost($model, $inputTokens, $outputTokens, $cacheTokens);

        $this->log($params, $content, $model, $purpose, $inputTokens, $outputTokens, $cacheTokens, $cost, $latencyMs, $data);

        return new LLMResponse(
            content:      $content,
            model:        $model,
            provider:     $this->getName(),
            inputTokens:  $inputTokens,
            outputTokens: $outputTokens,
            costUsd:      $cost,
            latencyMs:    $latencyMs,
            stopReason:   $data['stop_reason'] ?? 'end_turn',
            raw:          $data,
        );
    }

    private function log(
        array $params, string $content, string $model, string $purpose,
        int $inputTokens, int $outputTokens, int $cacheTokens,
        float $cost, int $latencyMs, array $raw,
    ): void {
        AIGenerationLog::create([
            'pipeline_run_id'   => $params['_pipeline_run_id'] ?? null,
            'persona_id'        => $params['_persona_id'] ?? null,
            'model'             => $model,
            'purpose'           => $purpose,
            'messages'          => $params['messages'] ?? [],
            'response'          => $content,
            'input_tokens'      => $inputTokens,
            'output_tokens'     => $outputTokens,
            'cache_read_tokens' => $cacheTokens,
            'cost_usd'          => $cost,
            'latency_ms'        => $latencyMs,
            'stop_reason'       => $raw['stop_reason'] ?? null,
            'metadata'          => [
                'provider'    => $this->getName(),
                'temperature' => $params['temperature'] ?? null,
            ],
        ]);
    }
}
