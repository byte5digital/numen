<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\Space;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookEventDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebhookEdgeCasesTest extends TestCase
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

    public function test_inactive_webhooks_are_not_triggered(): void
    {
        $activeWebhook = Webhook::factory()->create([
            'space_id' => $this->space->id,
            'is_active' => true,
            'events' => ['content.published'],
        ]);

        $inactiveWebhook = Webhook::factory()->create([
            'space_id' => $this->space->id,
            'is_active' => false,
            'events' => ['content.published'],
        ]);

        Bus::fake();

        $dispatcher = app(WebhookEventDispatcher::class);
        $dispatcher->dispatch('content.published', $this->space->id, [
            'id' => 'evt_123',
            'title' => 'Test',
        ]);

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_id' => $activeWebhook->id,
        ]);

        $this->assertDatabaseMissing('webhook_deliveries', [
            'webhook_id' => $inactiveWebhook->id,
        ]);

        Bus::assertDispatchedTimes(DeliverWebhook::class, 1);
    }

    public function test_can_toggle_webhook_active_status(): void
    {
        Sanctum::actingAs($this->user);

        $webhook = Webhook::factory()->create([
            'space_id' => $this->space->id,
            'is_active' => true,
        ]);

        $this->putJson("/api/v1/webhooks/{$webhook->id}", [
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_webhook_delivery_handles_http_failure(): void
    {
        $webhook = Webhook::factory()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
        ]);

        $payload = ['id' => 'evt_456', 'event' => 'content.published'];
        $delivery = WebhookDelivery::factory()->create([
            'webhook_id' => $webhook->id,
            'payload' => $payload,
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        Http::fake(['https://example.com/hook' => Http::response('Error', 500)]);

        $job = new DeliverWebhook($delivery, $payload);

        try {
            $job->handle();
        } catch (\Throwable $e) {
            // Expected
        }

        $delivery->refresh();
        $this->assertEquals(WebhookDelivery::STATUS_FAILED, $delivery->status);
    }

    public function test_webhook_includes_custom_headers(): void
    {
        $webhook = Webhook::factory()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'headers' => ['Authorization' => 'Bearer token-123'],
        ]);

        $payload = ['id' => 'evt_abc', 'event' => 'content.published'];
        $delivery = WebhookDelivery::factory()->create([
            'webhook_id' => $webhook->id,
            'payload' => $payload,
        ]);

        Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

        $job = new DeliverWebhook($delivery, $payload);
        $job->handle();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer token-123');
        });
    }

    public function test_webhook_matches_exact_event(): void
    {
        $webhook = Webhook::factory()->create(['events' => ['content.published']]);

        $this->assertTrue($webhook->matchesEvent('content.published'));
        $this->assertFalse($webhook->matchesEvent('content.updated'));
    }

    public function test_webhook_matches_domain_wildcard(): void
    {
        $webhook = Webhook::factory()->create(['events' => ['content.*']]);

        $this->assertTrue($webhook->matchesEvent('content.published'));
        $this->assertTrue($webhook->matchesEvent('content.updated'));
        $this->assertFalse($webhook->matchesEvent('pipeline.completed'));
    }

    public function test_webhook_matches_global_wildcard(): void
    {
        $webhook = Webhook::factory()->create(['events' => ['*']]);

        $this->assertTrue($webhook->matchesEvent('content.published'));
        $this->assertTrue($webhook->matchesEvent('pipeline.completed'));
    }

    public function test_successful_delivery_records_response(): void
    {
        $webhook = Webhook::factory()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
        ]);

        $payload = ['id' => 'evt_success', 'event' => 'content.published'];
        $delivery = WebhookDelivery::factory()->create([
            'webhook_id' => $webhook->id,
            'payload' => $payload,
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        Http::fake(['https://example.com/hook' => Http::response('{"ok": true}', 200)]);

        $job = new DeliverWebhook($delivery, $payload);
        $job->handle();

        $delivery->refresh();
        $this->assertEquals(WebhookDelivery::STATUS_DELIVERED, $delivery->status);
        $this->assertEquals(200, $delivery->http_status);
        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_unauthenticated_cannot_show_webhook(): void
    {
        $webhook = Webhook::factory()->create();
        $this->getJson("/api/v1/webhooks/{$webhook->id}")->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_redeliver(): void
    {
        $webhook = Webhook::factory()->create();
        $delivery = WebhookDelivery::factory()->create(['webhook_id' => $webhook->id]);

        $this->postJson(
            "/api/v1/webhooks/{$webhook->id}/deliveries/{$delivery->id}/redeliver"
        )->assertUnauthorized();
    }
}
