<?php

namespace App\Services\AI\Providers;

use App\Services\AI\CostTracker;
use App\Services\AI\LLMResponse;

/**
 * Azure AI Foundry provider.
 *
 * Azure uses the same chat completions API format as OpenAI but routes through
 * a per-deployment endpoint:
 *   https://{resource}.openai.azure.com/openai/deployments/{deployment}/chat/completions?api-version=2024-02-01
 *
 * The "model" field in params is treated as the deployment name.
 */
class AzureOpenAIProvider extends OpenAIProvider
{
    private string $endpoint;

    private string $apiVersion;

    private array $deploymentMap;

    public function __construct(CostTracker $costTracker)
    {
        parent::__construct($costTracker);
        $this->providerName = 'azure';
        $this->apiKey = (string) config('numen.providers.azure.api_key', '');
        $this->endpoint = rtrim((string) config('numen.providers.azure.endpoint', ''), '/');
        $this->apiVersion = (string) config('numen.providers.azure.api_version', '2024-02-01');

        // Map generic model names to Azure deployment names
        // Deployments can be named anything in Azure; configure in numen.providers.azure.deployments
        $this->deploymentMap = config('numen.providers.azure.deployments', [
            'gpt-5-mini' => 'gpt-5-mini',
            'gpt-5-nano' => 'gpt-5-nano',
        ]);
    }

    public function getName(): string
    {
        return 'azure';
    }

    public function isAvailable(string $model): bool
    {
        if (empty($this->apiKey) || empty($this->endpoint)) {
            return false;
        }

        $retryAfter = cache()->get("llm:rate:{$this->getName()}:{$model}:retry_after");

        return ! ($retryAfter && $retryAfter > now()->timestamp);
    }

    public function complete(array $params): LLMResponse
    {
        // Resolve the Azure deployment name from the model name
        $model = $params['model'] ?? 'gpt-5-mini';
        $deployment = $this->deploymentMap[$model] ?? $model;

        // Override base URL to Azure endpoint for this specific deployment
        $this->baseUrl = "{$this->endpoint}/openai/deployments/{$deployment}/chat/completions?api-version={$this->apiVersion}";

        // Azure doesn't use a path suffix — the full URL is already set above
        // We call parent::complete() but intercept the URL construction
        // Since parent uses $this->baseUrl . '/chat/completions', we strip that path out:
        $this->baseUrl = "{$this->endpoint}/openai/deployments/{$deployment}";

        return parent::complete(array_merge($params, ['model' => $deployment]));
    }

    protected function headers(): array
    {
        return [
            'api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}
