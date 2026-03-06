<?php

namespace Tests\Unit;

use App\Models\Persona;
use App\Models\Space;
use App\Services\AI\CostTracker;
use App\Services\AI\Contracts\LLMProvider;
use App\Services\AI\Exceptions\AllProvidersFailedException;
use App\Services\AI\Exceptions\CostLimitExceededException;
use App\Services\AI\Exceptions\ProviderRateLimitException;
use App\Services\AI\Exceptions\ProviderUnavailableException;
use App\Services\AI\LLMManager;
use App\Services\AI\LLMResponse;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\AzureOpenAIProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LLMManagerTest extends TestCase
{
    use RefreshDatabase;

    private AnthropicProvider $anthropic;
    private OpenAIProvider $openai;
    private AzureOpenAIProvider $azure;
    private CostTracker $costTracker;
    private LLMManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->anthropic   = $this->createMock(AnthropicProvider::class);
        $this->openai      = $this->createMock(OpenAIProvider::class);
        $this->azure       = $this->createMock(AzureOpenAIProvider::class);
        $this->costTracker = $this->createMock(CostTracker::class);

        $this->anthropic->method('getName')->willReturn('anthropic');
        $this->openai->method('getName')->willReturn('openai');
        $this->azure->method('getName')->willReturn('azure');

        $this->manager = new LLMManager(
            $this->anthropic,
            $this->openai,
            $this->azure,
            $this->costTracker,
        );
    }

    // --- Provider selection ---

    public function test_selects_anthropic_for_claude_model(): void
    {
        $this->costTracker->method('isWithinLimits')->willReturn(true);
        $this->costTracker->method('recordUsage')->willReturn(true);

        $this->anthropic->method('isAvailable')->willReturn(true);
        $this->anthropic->expects($this->once())
            ->method('complete')
            ->willReturn($this->makeResponse('anthropic', 'claude-sonnet-4-6'));

        $response = $this->manager->complete([
            'model'    => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $this->assertEquals('anthropic', $response->provider);
    }

    public function test_selects_openai_for_gpt_model(): void
    {
        $this->costTracker->method('isWithinLimits')->willReturn(true);
        $this->costTracker->method('recordUsage')->willReturn(true);

        $this->openai->method('isAvailable')->willReturn(true);
        $this->openai->expects($this->once())
            ->method('complete')
            ->willReturn($this->makeResponse('openai', 'gpt-4o'));

        $response = $this->manager->complete([
            'model'    => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $this->assertEquals('openai', $response->provider);
    }

    public function test_selects_provider_from_explicit_prefix(): void
    {
        $this->costTracker->method('isWithinLimits')->willReturn(true);
        $this->costTracker->method('recordUsage')->willReturn(true);

        $this->openai->method('isAvailable')->willReturn(true);
        $this->openai->expects($this->once())
            ->method('complete')
            ->with($this->arrayHasKey('model'))
            ->willReturn($this->makeResponse('openai', 'gpt-4o'));

        $response = $this->manager->complete([
            'model'    => 'openai:gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $this->assertEquals('openai', $response->provider);
    }

    public function test_uses_default_provider_when_model_is_empty(): void
    {
        config(['numen.default_provider' => 'anthropic']);
        config(['numen.providers.anthropic.default_model' => 'claude-sonnet-4-6']);

        $this->costTracker->method('isWithinLimits')->willReturn(true);
        $this->costTracker->method('recordUsage')->willReturn(true);

        $this->anthropic->method('isAvailable')->willReturn(true);
        $this->anthropic->expects($this->once())
            ->method('complete')
            ->willReturn($this->makeResponse('anthropic', 'claude-sonnet-4-6'));

        $response = $this->manager->complete([
            'model'    => '',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $this->assertEquals('anthropic', $response->provider);
    }

    // --- Fallback chain ---

    public function test_falls_back_to_openai_when_anthropic_rate_limited(): void
    {
        config(['numen.fallback_chain' => ['anthropic', 'openai', 'azure']]);

        $this->costTracker->method('isWithinLimits')->willReturn(true);
        $this->costTracker->method('recordUsage')->willReturn(true);

        $this->anthropic->method('isAvailable')->willReturn(true);
        $this->anthropic->expects($this->once())
            ->method('complete')
            ->willThrowException(new ProviderRateLimitException('anthropic', 'claude-sonnet-4-6', 60));

        $this->openai->method('isAvailable')->willReturn(true);
        $this->openai->expects($this->once())
            ->method('complete')
            ->willReturn($this->makeResponse('openai', 'gpt-4o'));

        $response = $this->manager->complete([
            'model'    => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $this->assertEquals('openai', $response->provider);
    }

    public function test_falls_back_when_provider_unavailable(): void
    {
        config(['numen.fallback_chain' => ['anthropic', 'openai', 'azure']]);

        $this->costTracker->method('isWithinLimits')->willReturn(true);
        $this->costTracker->method('recordUsage')->willReturn(true);

        $this->anthropic->method('isAvailable')->willReturn(true);
        $this->anthropic->method('complete')
            ->willThrowException(new ProviderUnavailableException('anthropic', 'claude-sonnet-4-6', 'HTTP 503'));

        $this->openai->method('isAvailable')->willReturn(true);
        $this->openai->method('complete')
            ->willReturn($this->makeResponse('openai', 'gpt-4o'));

        $response = $this->manager->complete([
            'model'    => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $this->assertEquals('openai', $response->provider);
    }

    public function test_throws_all_providers_failed_when_chain_exhausted(): void
    {
        config(['numen.fallback_chain' => ['anthropic', 'openai', 'azure']]);

        $this->costTracker->method('isWithinLimits')->willReturn(true);

        $exception = new ProviderRateLimitException('anthropic', 'claude-sonnet-4-6', 60);

        $this->anthropic->method('isAvailable')->willReturn(true);
        $this->anthropic->method('complete')->willThrowException($exception);

        $this->openai->method('isAvailable')->willReturn(true);
        $this->openai->method('complete')->willThrowException($exception);

        $this->azure->method('isAvailable')->willReturn(true);
        $this->azure->method('complete')->willThrowException($exception);

        $this->expectException(AllProvidersFailedException::class);

        $this->manager->complete([
            'model'    => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);
    }

    public function test_skips_unavailable_providers_in_chain(): void
    {
        config(['numen.fallback_chain' => ['anthropic', 'openai', 'azure']]);

        $this->costTracker->method('isWithinLimits')->willReturn(true);
        $this->costTracker->method('recordUsage')->willReturn(true);

        // anthropic not available (no API key / rate limited)
        $this->anthropic->method('isAvailable')->willReturn(false);
        $this->anthropic->expects($this->never())->method('complete');

        // openai not available
        $this->openai->method('isAvailable')->willReturn(false);
        $this->openai->expects($this->never())->method('complete');

        // azure available
        $this->azure->method('isAvailable')->willReturn(true);
        $this->azure->expects($this->once())
            ->method('complete')
            ->willReturn($this->makeResponse('azure', 'gpt-4o'));

        $response = $this->manager->complete([
            'model'    => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $this->assertEquals('azure', $response->provider);
    }

    // --- Cost limit enforcement ---

    public function test_throws_cost_limit_exceeded_on_preflight_check(): void
    {
        $this->costTracker->method('isWithinLimits')->willReturn(false);

        $this->anthropic->expects($this->never())->method('complete');
        $this->openai->expects($this->never())->method('complete');

        $this->expectException(CostLimitExceededException::class);

        $this->manager->complete([
            'model'    => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);
    }

    // --- createMessage legacy wrapper ---

    public function test_create_message_returns_anthropic_style_format(): void
    {
        $this->costTracker->method('isWithinLimits')->willReturn(true);
        $this->costTracker->method('recordUsage')->willReturn(true);

        $this->anthropic->method('isAvailable')->willReturn(true);
        $this->anthropic->method('complete')
            ->willReturn($this->makeResponse('anthropic', 'claude-sonnet-4-6', 'Hello world', 100, 50));

        $result = $this->manager->createMessage([
            'model'    => 'claude-sonnet-4-6',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('stop_reason', $result);
        $this->assertArrayHasKey('usage', $result);
        $this->assertArrayHasKey('_provider', $result);
        $this->assertArrayHasKey('_cost_usd', $result);

        $this->assertEquals('anthropic', $result['_provider']);
        $this->assertEquals('Hello world', $result['content'][0]['text']);
        $this->assertEquals(100, $result['usage']['input_tokens']);
        $this->assertEquals(50, $result['usage']['output_tokens']);
    }

    public function test_extract_text_content_from_legacy_response(): void
    {
        $response = [
            'content' => [
                ['type' => 'text', 'text' => 'First paragraph'],
                ['type' => 'text', 'text' => 'Second paragraph'],
            ],
        ];

        $text = $this->manager->extractTextContent($response);

        $this->assertStringContainsString('First paragraph', $text);
        $this->assertStringContainsString('Second paragraph', $text);
    }

    // --- Persona fallback model ---

    public function test_uses_persona_fallback_model_when_primary_fails(): void
    {
        config(['numen.fallback_chain' => ['anthropic', 'openai', 'azure']]);

        $this->costTracker->method('isWithinLimits')->willReturn(true);
        $this->costTracker->method('recordUsage')->willReturn(true);

        $space = Space::factory()->create();
        $persona = Persona::factory()->create([
            'space_id'     => $space->id,
            'model_config' => [
                'model'            => 'claude-sonnet-4-6',
                'fallback_model'   => 'gpt-4o',
                'fallback_provider'=> 'openai',
            ],
        ]);

        $this->anthropic->method('isAvailable')->willReturn(true);
        $this->anthropic->method('complete')
            ->willThrowException(new ProviderRateLimitException('anthropic', 'claude-sonnet-4-6', 60));

        $this->openai->method('isAvailable')->willReturn(true);
        $this->openai->expects($this->once())
            ->method('complete')
            ->with($this->callback(fn ($p) => $p['model'] === 'gpt-4o'))
            ->willReturn($this->makeResponse('openai', 'gpt-4o'));

        $response = $this->manager->complete(
            ['model' => 'claude-sonnet-4-6', 'messages' => [['role' => 'user', 'content' => 'Hi']]],
            null,
            $persona,
        );

        $this->assertEquals('openai', $response->provider);
    }

    // --- Helper ---

    private function makeResponse(
        string $provider,
        string $model,
        string $content = 'Test response',
        int $inputTokens = 100,
        int $outputTokens = 50,
    ): LLMResponse {
        return new LLMResponse(
            content:      $content,
            model:        $model,
            provider:     $provider,
            inputTokens:  $inputTokens,
            outputTokens: $outputTokens,
            costUsd:      0.001,
            latencyMs:    200,
        );
    }
}
