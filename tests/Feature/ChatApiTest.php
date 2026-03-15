<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Space;
use App\Models\User;
use App\Services\AI\LLMManager;
use App\Services\AI\LLMResponse;
use App\Services\Chat\ChatRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->space = Space::factory()->create();
    }

    /** @test */
    public function test_user_can_create_conversation(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/chat/conversations', [
                'space_id' => $this->space->id,
                'title' => 'My Test Conversation',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.space_id', $this->space->id)
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.title', 'My Test Conversation');

        $this->assertDatabaseHas('chat_conversations', [
            'space_id' => $this->space->id,
            'user_id' => $this->user->id,
            'title' => 'My Test Conversation',
        ]);
    }

    /** @test */
    public function test_user_can_list_conversations(): void
    {
        ChatConversation::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'space_id' => $this->space->id,
        ]);

        // Another user's conversation — should not appear
        $otherUser = User::factory()->create();
        ChatConversation::factory()->create([
            'user_id' => $otherUser->id,
            'space_id' => $this->space->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/chat/conversations');

        $response->assertOk()
            ->assertJsonCount(3, 'data.data');
    }

    /** @test */
    public function test_user_can_send_message(): void
    {
        $fakeResponse = new LLMResponse(
            content: json_encode([
                'message' => 'Hello! Here are your contents.',
                'intent' => [
                    'action' => 'content.query',
                    'entity' => 'content',
                    'params' => [],
                    'confidence' => 0.95,
                    'requires_confirmation' => false,
                ],
            ]),
            inputTokens: 100,
            outputTokens: 50,
            costUsd: 0.001,
            model: 'claude-haiku-4-5-20251001',
            latencyMs: 100,
            provider: 'anthropic',
        );

        $this->mock(LLMManager::class)
            ->shouldReceive('complete')
            ->once()
            ->andReturn($fakeResponse);

        $conversation = ChatConversation::factory()->create([
            'user_id' => $this->user->id,
            'space_id' => $this->space->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'message' => 'Show me all published content',
            ]);

        $response->assertOk();

        // Trigger stream callback so DB writes happen
        $response->streamedContent();

        // Stream response — check that user message was stored
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Show me all published content',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
        ]);
    }

    /** @test */
    public function test_user_can_view_message_history(): void
    {
        $conversation = ChatConversation::factory()->create([
            'user_id' => $this->user->id,
            'space_id' => $this->space->id,
        ]);

        ChatMessage::factory()->count(5)->create([
            'conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/chat/conversations/{$conversation->id}/messages");

        $response->assertOk()
            ->assertJsonCount(5, 'data.data');
    }

    /** @test */
    public function test_user_can_delete_conversation(): void
    {
        $conversation = ChatConversation::factory()->create([
            'user_id' => $this->user->id,
            'space_id' => $this->space->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/chat/conversations/{$conversation->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('chat_conversations', [
            'id' => $conversation->id,
        ]);
    }

    /** @test */
    public function test_confirmation_flow(): void
    {
        $pendingAction = [
            'action' => 'content.delete',
            'params' => ['content_id' => 'test-id'],
            'message' => 'This will permanently delete the content.',
        ];

        $conversation = ChatConversation::factory()
            ->withPendingAction($pendingAction)
            ->create([
                'user_id' => $this->user->id,
                'space_id' => $this->space->id,
            ]);

        // Verify pending_action is stored
        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversation->id,
        ]);
        $this->assertNotNull($conversation->fresh()->pending_action);

        // Confirm the action
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.confirmed', true)
            ->assertJsonPath('data.action.action', 'content.delete');

        // pending_action should be cleared
        $this->assertNull($conversation->fresh()->pending_action);
    }

    /** @test */
    public function test_cancel_confirmation(): void
    {
        $conversation = ChatConversation::factory()
            ->withPendingAction()
            ->create([
                'user_id' => $this->user->id,
                'space_id' => $this->space->id,
            ]);

        $this->assertNotNull($conversation->fresh()->pending_action);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/chat/conversations/{$conversation->id}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.cancelled', true);

        $this->assertNull($conversation->fresh()->pending_action);
    }

    /** @test */
    public function test_rate_limiting(): void
    {
        $conversation = ChatConversation::factory()->create([
            'user_id' => $this->user->id,
            'space_id' => $this->space->id,
        ]);

        // Mock the rate limiter to return false (over limit)
        $this->mock(ChatRateLimiter::class)
            ->shouldReceive('check')
            ->once()
            ->andReturn(false)
            ->shouldReceive('getRemainingQuota')
            ->once()
            ->andReturn([
                'messages_remaining' => 0,
                'cost_remaining' => 0.5,
                'resets_at' => now()->addMinute()->toIso8601String(),
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'message' => 'Hello',
            ]);

        $response->assertStatus(429)
            ->assertJsonPath('error', 'Rate limit exceeded.');
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_chat(): void
    {
        $this->getJson('/api/v1/chat/conversations')
            ->assertUnauthorized();

        $this->postJson('/api/v1/chat/conversations', ['space_id' => $this->space->id])
            ->assertUnauthorized();
    }

    /** @test */
    public function test_user_can_only_see_own_conversations(): void
    {
        $otherUser = User::factory()->create();

        $ownConversation = ChatConversation::factory()->create([
            'user_id' => $this->user->id,
            'space_id' => $this->space->id,
        ]);

        $otherConversation = ChatConversation::factory()->create([
            'user_id' => $otherUser->id,
            'space_id' => $this->space->id,
        ]);

        // Can access own messages
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/chat/conversations/{$ownConversation->id}/messages")
            ->assertOk();

        // Cannot access other user's conversation
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/chat/conversations/{$otherConversation->id}/messages")
            ->assertNotFound();

        // Cannot delete other user's conversation
        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/chat/conversations/{$otherConversation->id}")
            ->assertNotFound();
    }
}
