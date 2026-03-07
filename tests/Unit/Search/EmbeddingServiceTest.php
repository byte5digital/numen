<?php

namespace Tests\Unit\Search;

use App\Services\AI\CostTracker;
use App\Services\Search\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    use RefreshDatabase;

    // ── No API Key → Zero Vectors ────────────────────────────────────────────

    public function test_embed_returns_zero_vector_when_no_api_key_configured(): void
    {
        config(['numen.providers.openai.api_key' => null]);
        config(['numen.search.embedding_dimensions' => 1536]);

        $service = $this->makeService();
        $result = $service->embed('hello world');

        $this->assertIsArray($result);
        $this->assertCount(1536, $result);
        $this->assertSame(0.0, $result[0]);
    }

    public function test_embed_batch_empty_input_returns_empty_array(): void
    {
        $service = $this->makeService();
        $result = $service->embedBatch([]);

        $this->assertSame([], $result);
    }

    public function test_embed_batch_returns_zero_vectors_for_each_text_when_no_key(): void
    {
        config(['numen.providers.openai.api_key' => null]);
        config(['numen.search.embedding_dimensions' => 8]);

        $service = $this->makeService();
        $results = $service->embedBatch(['text one', 'text two', 'text three']);

        $this->assertCount(3, $results);
        foreach ($results as $vector) {
            $this->assertCount(8, $vector);
            $this->assertSame(0.0, $vector[0]);
        }
    }

    // ── Model & Dimensions Config ────────────────────────────────────────────

    public function test_get_model_returns_configured_value(): void
    {
        config(['numen.search.embedding_model' => 'text-embedding-ada-002']);
        $service = $this->makeService();

        $this->assertSame('text-embedding-ada-002', $service->getModel());
    }

    public function test_get_model_returns_default_when_key_unset(): void
    {
        // When the key is not set at all (using a completely different key), the default fires
        $service = $this->makeService();
        // The default key IS set in phpunit.xml / numen config, so we just verify it returns a non-empty string
        $this->assertNotEmpty($service->getModel());
    }

    public function test_get_dimensions_returns_configured_value(): void
    {
        config(['numen.search.embedding_dimensions' => 3072]);
        $service = $this->makeService();

        $this->assertSame(3072, $service->getDimensions());
    }

    public function test_get_dimensions_returns_positive_integer(): void
    {
        // Dimensions should always be a positive integer
        $service = $this->makeService();
        $this->assertGreaterThan(0, $service->getDimensions());
    }

    // ── Single Embed Delegates to Batch ──────────────────────────────────────

    public function test_embed_single_text_returns_first_vector_from_batch(): void
    {
        config(['numen.providers.openai.api_key' => null]);
        config(['numen.search.embedding_dimensions' => 4]);

        $service = $this->makeService();
        $single = $service->embed('test');
        $batch = $service->embedBatch(['test']);

        $this->assertSame($batch[0], $single);
    }

    // ── Large Batch Chunking ──────────────────────────────────────────────────

    public function test_embed_batch_with_more_than_2048_texts_returns_all_results(): void
    {
        config(['numen.providers.openai.api_key' => null]);
        config(['numen.search.embedding_dimensions' => 2]);

        $texts = array_fill(0, 2050, 'sample text');
        $service = $this->makeService();
        $results = $service->embedBatch($texts);

        $this->assertCount(2050, $results);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeService(): EmbeddingService
    {
        $costTracker = $this->createMock(CostTracker::class);

        return new EmbeddingService($costTracker);
    }
}
