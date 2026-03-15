<?php

namespace Tests\Unit;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Space;
use App\Models\User;
use App\Services\AI\LLMManager;
use App\Services\AI\LLMResponse;
use App\Services\Chat\ConversationContextManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationContextManagerTest extends TestCase
{
    use RefreshDatabase;

    private ConversationContextManager $contextManager;

    private ChatConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $mockLLM = $this->createMock(LLMManager::class);
        $this->contextManager = new ConversationContextManager($mockLLM);

        $user = User::factory()->create();
        $space = Space::factory()->create();

        $this->conversation = ChatConversation::factory()->create([
            'user_id' => $user->id,
            'space_id' => $space->id,
        ]);
    }

    /** @test */
    public function test_builds_context_window(): void
    {
        ChatMessage::factory()->count(5)->create([
            'conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Test message',
        ]);

        $context = $this->contextManager->buildContext($this->conversation, 15);

        $this->assertCount(5, $context);
        $this->assertArrayHasKey('role', $context[0]);
        $this->assertArrayHasKey('content', $context[0]);
        $this->assertEquals('user', $context[0]['role']);
    }

    /** @test */
    public function test_summarizes_old_messages(): void
    {
        // Create 35 messages (above SUMMARY_THRESHOLD of 30)
        for ($i = 0; $i < 35; $i++) {
            ChatMessage::factory()->create([
                'conversation_id' => $this->conversation->id,
                'role' => $i % 2 === 0 ? 'user' : 'assistant',
                'content' => "Message {$i}",
            ]);
        }

        $fakeSummaryResponse = new LLMResponse(
            content: 'The user asked about content management and received helpful answers.',
            inputTokens: 400,
            outputTokens: 60,
            costUsd: 0.0005,
            model: 'claude-haiku-4-5-20251001',
            provider: 'anthropic',
            latencyMs: 200,
        );

        $mockLLM = $this->createMock(LLMManager::class);
        $mockLLM->expects($this->once())
            ->method('complete')
            ->willReturn($fakeSummaryResponse);

        $contextManager = new ConversationContextManager($mockLLM);
        $contextManager->summarizeOlder($this->conversation);

        $this->conversation->refresh();
        $context = $this->conversation->context ?? [];

        $this->assertArrayHasKey('summary', $context);
        $this->assertEquals(
            'The user asked about content management and received helpful answers.',
            $context['summary']
        );
    }

    /** @test */
    public function test_full_context_includes_summary(): void
    {
        // Pre-set a summary in context
        $this->conversation->update([
            'context' => [
                'summary' => 'Earlier the user asked about publishing content.',
                'summary_covers_up_to' => 'msg-001',
            ],
        ]);

        // Add some recent messages
        ChatMessage::factory()->count(3)->create([
            'conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Recent message',
        ]);

        $this->conversation->refresh();

        $fullContext = $this->contextManager->getFullContext($this->conversation);

        // Should have 2 summary messages + 3 recent messages = 5 total
        $this->assertCount(5, $fullContext);

        // First message should be the synthetic summary exchange
        $this->assertEquals('user', $fullContext[0]['role']);
        $this->assertEquals('[Earlier conversation summary]', $fullContext[0]['content']);

        $this->assertEquals('assistant', $fullContext[1]['role']);
        $this->assertEquals('Earlier the user asked about publishing content.', $fullContext[1]['content']);
    }
}
