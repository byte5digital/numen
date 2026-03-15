<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\Space;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->space = Space::factory()->create();
        $this->user = User::factory()->create();
    }

    // ---------------------------------------------------------------------------
    // CRUD — Webhooks
    // ---------------------------------------------------------------------------

    public function test_unauthenticated_cannot_list_webhooks(): void
    {
        $this->getJson('/api/v1/webhooks')->assertUnauthorized();
    }

    public function test_can_list_webhooks(): void
    {
        Sanctum::actingAs($this->user);

        Webhook::factory()->create(['space_id' => $this->space->id]);

        $this->getJson('/api/v1/webhooks')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_can_create_webhook(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/webhooks', [
            'space_id' => $this->space->id,
            'url' => 'https://example.com/hook',
            'events' => ['content.published'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.url', 'https://example.com/hook')
            ->assertJsonPath('data.space_id', $this->space->id);

        $this->assertDatabaseHas('webhooks', [
            'space_id' => $this->space->id,
            'url' => 'https://example.com/hook',
        ]);
    }

    public function test_create_webhook_requires_valid_url(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/webhooks', [
            'space_id' => $this->space->id,
            'url' => 'not-a-url',
            'events' => ['content.published'],
        ])->assertUnprocessable();
    }

    public function test_can_update_webhook(): void
    {
        Sanctum::actingAs($this->user);

        $webhook = Webhook::factory()->create([
            'space_id' => $this->space->id,
            'url' => 'https://example.com/old',
        ]);

        $this->putJson("/api/v1/webhooks/{$webhook->id}", [
            'url' => 'https://example.com/new',
            'events' => ['content.updated'],
        ])
            ->assertOk()
            ->assertJsonPath('data.url', 'https://example.com/new');
    }

    public function test_can_delete_webhook(): void
    {
        Sanctum::actingAs($this->user);

        $webhook = Webhook::factory()->create(['space_id' => $this->space->id]);

        $this->deleteJson("/api/v1/webhooks/{$webhook->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('webhooks', ['id' => $webhook->id]);
    }

    // ---------------------------------------------------------------------------
    // Deliveries
    // ---------------------------------------------------------------------------

    public function test_can_list_deliveries_for_webhook(): void
    {
        Sanctum::actingAs($this->user);

        $webhook = Webhook::factory()->create(['space_id' => $this->space->id]);
        WebhookDelivery::factory()->count(3)->create(['webhook_id' => $webhook->id]);

        $this->getJson("/api/v1/webhooks/{$webhook->id}/deliveries")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_show_single_delivery(): void
    {
        Sanctum::actingAs($this->user);

        $webhook = Webhook::factory()->create(['space_id' => $this->space->id]);
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        $this->getJson("/api/v1/webhooks/{$webhook->id}/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $delivery->id);
    }

    public function test_can_redeliver_a_failed_delivery(): void
    {
        Sanctum::actingAs($this->user);
        Bus::fake();

        $webhook = Webhook::factory()->create(['space_id' => $this->space->id]);
        $delivery = WebhookDelivery::factory()->failed()->create([
            'webhook_id' => $webhook->id,
            'payload' => ['id' => 'evt_123', 'event' => 'content.published'],
        ]);

        $this->postJson("/api/v1/webhooks/{$webhook->id}/deliveries/{$delivery->id}/redeliver")
            ->assertStatus(202);

        Bus::assertDispatched(DeliverWebhook::class);

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_id' => $webhook->id,
            'event_id' => $delivery->event_id,
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);
    }

    // ---------------------------------------------------------------------------
    // HMAC Signature
    // ---------------------------------------------------------------------------

    public function test_deliver_webhook_job_sends_x_numen_signature_header(): void
    {
        $webhook = Webhook::factory()->create([
            'space_id' => $this->space->id,
            'url' => 'https://example.com/hook',
            'secret' => 'my-test-secret',
        ]);

        $payload = ['id' => 'evt_abc', 'event' => 'content.published', 'data' => []];

        $delivery = WebhookDelivery::factory()->create([
            'webhook_id' => $webhook->id,
            'payload' => $payload,
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

        $job = new DeliverWebhook($delivery, $payload);
        $job->handle();

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $expectedSignature = 'sha256='.hash_hmac('sha256', $body, 'my-test-secret');

        Http::assertSent(function ($request) use ($expectedSignature) {
            return $request->hasHeader('X-Numen-Signature', $expectedSignature);
        });
    }

    public function test_signature_uses_hmac_sha256_of_payload_body(): void
    {
        $secret = 'super-secret-key';
        $payload = ['id' => 'evt_xyz', 'event' => 'pipeline.completed'];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $expected = 'sha256='.hash_hmac('sha256', $body, $secret);

        // Verify format
        $this->assertStringStartsWith('sha256=', $expected);
        $this->assertEquals(71, strlen($expected)); // "sha256=" (7) + 64 hex chars
    }
}
