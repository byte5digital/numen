<?php

namespace Tests\Unit;

use App\Services\AI\CostTracker;
use App\Services\AI\Exceptions\ProviderRateLimitException;
use App\Services\AI\Exceptions\ProviderUnavailableException;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIProviderTest extends TestCase
{
    use RefreshDatabase;

    private OpenAIProvider $provider;

    private CostTracker $costTracker;

    protected function setUp(): void
    {
        parent::setUp();

        config(['numen.providers.openai.api_key' => 'sk-test-openai-key-123']);
        config(['numen.providers.openai.base_url' => 'https://api.openai.com/v1']);

        $this->costTracker = $this->createMock(CostTracker::class);
        $this->provider = new OpenAIProvider($this->costTracker);
    }

    // --- Request building ---

    public function test_sends_bearer_auth_header(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->successResponse(), 200),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.001);

        $this->provider->complete([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer sk-test-openai-key-123');
        });
    }

    public function test_converts_system_prompt_to_first_message(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->successResponse(), 200),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.001);

        $this->provider->complete([
            'model' => 'gpt-4o',
            'system' => 'You are an expert writer.',
            'messages' => [['role' => 'user', 'content' => 'Write something']],
        ]);

        Http::assertSent(function ($request) {
            $messages = $request->data()['messages'];

            return $messages[0]['role'] === 'system'
                && $messages[0]['content'] === 'You are an expert writer.'
                && $messages[1]['role'] === 'user';
        });
    }

    public function test_uses_max_completion_tokens_for_gpt4o(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->successResponse(), 200),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.001);

        $this->provider->complete([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
            'max_tokens' => 2048,
        ]);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['max_completion_tokens'])
                && $data['max_completion_tokens'] === 2048
                && ! isset($data['max_tokens']);
        });
    }

    public function test_uses_max_tokens_for_older_models(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->successResponse(), 200),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.001);

        $this->provider->complete([
            'model' => 'gpt-3.5-turbo',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
            'max_tokens' => 2048,
        ]);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['max_tokens'])
                && $data['max_tokens'] === 2048
                && ! isset($data['max_completion_tokens']);
        });
    }

    // --- Message format conversion ---

    public function test_no_extra_system_message_when_system_empty(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->successResponse(), 200),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.001);

        $this->provider->complete([
            'model' => 'gpt-4o',
            'system' => '',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        Http::assertSent(function ($request) {
            $messages = $request->data()['messages'];

            return count($messages) === 1 && $messages[0]['role'] === 'user';
        });
    }

    // --- Response parsing ---

    public function test_extracts_content_from_choices(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->successResponse('The answer is 42'), 200),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.001);

        $response = $this->provider->complete([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'What is the answer?']],
        ]);

        $this->assertEquals('The answer is 42', $response->content);
        $this->assertEquals('openai', $response->provider);
        $this->assertEquals('gpt-4o', $response->model);
    }

    public function test_reads_token_counts_from_usage(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->successResponse('Answer', 200, 80), 200),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.001);

        $response = $this->provider->complete([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $this->assertEquals(200, $response->inputTokens);
        $this->assertEquals(80, $response->outputTokens);
    }

    public function test_records_stop_reason_from_finish_reason(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->successResponse('Done', 100, 50, 'length'), 200),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.001);

        $response = $this->provider->complete([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $this->assertEquals('length', $response->stopReason);
    }

    // --- Error handling ---

    public function test_throws_rate_limit_on_429(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429),
        ]);

        $this->expectException(ProviderRateLimitException::class);

        $this->provider->complete([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);
    }

    public function test_throws_rate_limit_on_400_quota_exceeded(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'You have exceeded your usage quota for this period'],
            ], 400),
        ]);

        $this->expectException(ProviderRateLimitException::class);

        $this->provider->complete([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);
    }

    public function test_throws_unavailable_on_500(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('Server error', 500),
        ]);

        $this->expectException(ProviderUnavailableException::class);

        $this->provider->complete([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);
    }

    public function test_throws_unavailable_on_connection_error(): void
    {
        Http::fake([
            'api.openai.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Timeout'),
        ]);

        $this->expectException(ProviderUnavailableException::class);

        $this->provider->complete([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);
    }

    // --- isAvailable ---

    public function test_not_available_when_api_key_missing(): void
    {
        config(['numen.providers.openai.api_key' => '']);

        $provider = new OpenAIProvider($this->costTracker);

        $this->assertFalse($provider->isAvailable('gpt-4o'));
    }

    public function test_available_when_api_key_set(): void
    {
        $this->assertTrue($this->provider->isAvailable('gpt-4o'));
    }

    // --- Provider name ---

    public function test_provider_name_is_openai(): void
    {
        $this->assertEquals('openai', $this->provider->getName());
    }

    // --- Helpers ---

    private function successResponse(
        string $content = 'Test response',
        int $promptTokens = 100,
        int $completionTokens = 50,
        string $finishReason = 'stop',
    ): array {
        return [
            'id' => 'chatcmpl-test123',
            'object' => 'chat.completion',
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => $content],
                    'finish_reason' => $finishReason,
                ],
            ],
            'usage' => [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
            ],
        ];
    }
}
