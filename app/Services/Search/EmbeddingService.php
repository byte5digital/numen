<?php

namespace App\Services\Search;

use App\Models\AIGenerationLog;
use App\Services\AI\CostTracker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates text embeddings via OpenAI API.
 * Uses text-embedding-3-small by default (1536 dimensions, low cost).
 * Batches up to 2048 texts per API call.
 */
class EmbeddingService
{
    private const MAX_BATCH_SIZE = 2048;

    public function __construct(
        private readonly CostTracker $costTracker,
    ) {}

    /**
     * Embed a single text string.
     *
     * @return float[]
     */
    public function embed(string $text): array
    {
        $results = $this->embedBatch([$text]);

        return $results[0] ?? [];
    }

    /**
     * Embed multiple texts in a single API call.
     *
     * @param  string[]  $texts
     * @return float[][]
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $chunks = array_chunk($texts, self::MAX_BATCH_SIZE);
        $embeddings = [];

        foreach ($chunks as $chunk) {
            $batch = $this->callOpenAIEmbeddings($chunk);
            foreach ($batch as $embedding) {
                $embeddings[] = $embedding;
            }
        }

        return $embeddings;
    }

    public function getModel(): string
    {
        return (string) config('numen.search.embedding_model', 'text-embedding-3-small');
    }

    public function getDimensions(): int
    {
        return (int) config('numen.search.embedding_dimensions', 1536);
    }

    /**
     * @param  string[]  $texts
     * @return float[][]
     */
    private function callOpenAIEmbeddings(array $texts): array
    {
        $apiKey = config('numen.providers.openai.api_key');
        $baseUrl = config('numen.providers.openai.base_url', 'https://api.openai.com/v1');

        if (! $apiKey) {
            Log::warning('EmbeddingService: no OpenAI API key configured, returning zero vectors');

            return array_fill(0, count($texts), array_fill(0, $this->getDimensions(), 0.0));
        }

        $httpResponse = Http::withToken($apiKey)
            ->timeout(60)
            ->retry(2, 500, throw: false)
            ->post("{$baseUrl}/embeddings", [
                'model' => $this->getModel(),
                'input' => $texts,
                'encoding_format' => 'float',
            ]);

        if ($httpResponse->failed()) {
            throw new \RuntimeException("OpenAI embeddings API error (HTTP {$httpResponse->status()})");
        }

        /** @var array<string, mixed> $response */
        $response = $httpResponse->json();

        // Track cost (text-embedding-3-small: $0.02/1M tokens)
        $tokensUsed = (int) ($response['usage']['total_tokens'] ?? 0);
        $costUsd = $tokensUsed * 0.00000002;
        $this->costTracker->recordUsage($costUsd);

        AIGenerationLog::create([
            'model' => $this->getModel(),
            'provider' => 'openai',
            'purpose' => 'embeddings',
            'input_tokens' => $tokensUsed,
            'output_tokens' => 0,
            'cost_usd' => $costUsd,
        ]);

        /** @var array<int, array<string, mixed>> $data */
        $data = $response['data'] ?? [];

        // Sort by index to preserve order
        usort($data, fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return array_map(fn ($item) => (array) ($item['embedding'] ?? []), $data);
    }
}
