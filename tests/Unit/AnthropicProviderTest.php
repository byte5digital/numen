<?php

namespace Tests\Unit;

use App\Services\AI\CostTracker;
use App\Services\AI\Exceptions\ProviderRateLimitException;
use App\Services\AI\Exceptions\ProviderUnavailableException;
use App\Services\AI\Providers\AnthropicProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnthropicProviderTest extends TestCase
{
    use RefreshDatabase;

    private AnthropicProvider $provider;

    private CostTracker $costTracker;

    protected function setUp(): void
    {
        parent::setUp();

        // Set a fake API key so isAvailable() returns true
        config(['numen.providers.anthropic.api_key' => 'test-key-sk-ant-123']);
        config(['numen.providers.anthropic.base_url' => 'https://api.anthropic.com']);

        $this->costTracker = $this->createMock(CostTracker::class);
        $this->provider = new AnthropicProvider($this->costTracker);
    }

    // --- Request building ---

    public function test_sends_correct_headers(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->successResponse(), 200),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.001);

        $this->provider->complete([
            'model' => 'claude-sonnet-4-6',
            'system' => 'You are helpful.',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        Http::assertSent(function ($request) {
            return $request->hasHeader('x-api-key', 'test-key-sk-ant-123')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $request->hasHeader('content-type', 'application/json');
        });
    }

    public function test_sends_system_and_messages_in_request_body(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->successResponse(), 200),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.001);

        $this->provider->complete([
            'model' => 'claude-sonnet-4-6',
            'system' => 'Be helpful.',
            'messages' => [['role' => 'user', 'content' => 'Tell me a joke']],
            'max_tokens' => 1024,
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return isset($body['system'])
                && $body['system'] === 'Be helpful.'
                && isset($body['messages'])
                && $body['messages'][0]['content'] === 'Tell me a joke'
                && $body['max_tokens'] === 1024;
        });
    }

    // --- Response parsing ---

    public function test_extracts_text_content_from_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->successResponse('This is the answer'), 200),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.002);

        $response = $this->provider->complete([
            'model' => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Question?']],
        ]);

        $this->assertEquals('This is the answer', $response->content);
        $this->assertEquals('anthropic', $response->provider);
        $this->assertEquals('claude-sonnet-4-6', $response->model);
    }

    public function test_counts_tokens_from_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->successResponse('Answer', 150, 75), 200),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.003);

        $response = $this->provider->complete([
            'model' => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Question?']],
        ]);

        $this->assertEquals(150, $response->inputTokens);
        $this->assertEquals(75, $response->outputTokens);
        $this->assertEquals(225, $response->totalTokens());
    }

    public function test_records_stop_reason(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(
                array_merge($this->successResponse(), ['stop_reason' => 'max_tokens']),
                200,
            ),
        ]);

        $this->costTracker->method('calculateCost')->willReturn(0.001);

        $response = $this->provider->complete([
            'model' => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $this->assertEquals('max_tokens', $response->stopReason);
    }

    // --- Error handling ---

    public function test_throws_rate_limit_exception_on_429(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => ['message' => 'Rate limited']], 429, [
                'retry-after' => '30',
            ]),
        ]);

        $this->expectException(ProviderRateLimitException::class);

        $this->provider->complete([
            'model' => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);
    }

    public function test_caches_retry_after_on_rate_limit(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => ['message' => 'Rate limited']], 429, [
                'retry-after' => '60',
            ]),
        ]);

        try {
            $this->provider->complete([
                'model' => 'claude-sonnet-4-6',
                'messages' => [['role' => 'user', 'content' => 'Hello']],
            ]);
        } catch (ProviderRateLimitException) {
            // Expected
        }

        // After rate limit, isAvailable() should return false
        $this->assertFalse($this->provider->isAvailable('claude-sonnet-4-6'));
    }

    public function test_throws_rate_limit_on_400_workspace_usage_limit(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'error' => ['message' => 'Your workspace api usage limit has been reached'],
            ], 400),
        ]);

        $this->expectException(ProviderRateLimitException::class);

        $this->provider->complete([
            'model' => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);
    }

    public function test_throws_unavailable_on_500(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(ProviderUnavailableException::class);

        $this->provider->complete([
            'model' => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);
    }

    public function test_throws_unavailable_on_network_error(): void
    {
        Http::fake([
            'api.anthropic.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'),
        ]);

        $this->expectException(ProviderUnavailableException::class);

        $this->provider->complete([
            'model' => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);
    }

    // --- isAvailable ---

    public function test_not_available_when_api_key_empty(): void
    {
        config(['numen.providers.anthropic.api_key' => '']);

        $provider = new AnthropicProvider($this->costTracker);

        $this->assertFalse($provider->isAvailable('claude-sonnet-4-6'));
    }

    public function test_available_when_api_key_set_and_not_rate_limited(): void
    {
        $this->assertTrue($this->provider->isAvailable('claude-sonnet-4-6'));
    }

    // --- Cost calculation ---

    public function test_calculates_cost_via_cost_tracker(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->successResponse('Test', 100, 50), 200),
        ]);

        $this->costTracker->expects($this->once())
            ->method('calculateCost')
            ->with('claude-sonnet-4-6', 100, 50, 0)
            ->willReturn(0.00105);

        $response = $this->provider->complete([
            'model' => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $this->assertEqualsWithDelta(0.00105, $response->costUsd, 0.00001);
    }

    public function test_includes_cache_tokens_in_cost_calculation(): void
    {
        $responseData = $this->successResponse('Test', 100, 50);
        $responseData['usage']['cache_read_input_tokens'] = 800;

        Http::fake([
            'api.anthropic.com/*' => Http::response($responseData, 200),
        ]);

        $this->costTracker->expects($this->once())
            ->method('calculateCost')
            ->with('claude-sonnet-4-6', 100, 50, 800)
            ->willReturn(0.0005);

        $this->provider->complete([
            'model' => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);
    }

    // --- Helpers ---

    private function successResponse(
        string $text = 'Test response',
        int $inputTokens = 100,
        int $outputTokens = 50,
    ): array {
        return [
            'id' => 'msg_01test',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => $text],
            ],
            'model' => 'claude-sonnet-4-6',
            'stop_reason' => 'end_turn',
            'usage' => [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cache_read_input_tokens' => 0,
            ],
        ];
    }
}
