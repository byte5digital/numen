<?php

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Models\WebhookDelivery;

class WebhookEventDispatcher
{
    public function __construct(private readonly EventMapper $mapper) {}

    /**
     * Dispatch webhook deliveries for a domain event.
     *
     * Finds all active webhooks for the given space that subscribe to this
     * event type, maps the payload, records a WebhookDelivery, and queues
     * a DeliverWebhook job for each.
     *
     * @param  string  $eventType  e.g. "content.published"
     * @param  string  $spaceId  The space this event belongs to
     * @param  array  $context  Model data used to build the payload
     */
    public function dispatch(string $eventType, string $spaceId, array $context): void
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Webhook> $webhooks */
        $webhooks = Webhook::query()
            ->where('space_id', $spaceId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Webhook $webhook) => $webhook->matchesEvent($eventType));

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = $this->mapper->map($eventType, $context);
        $payloadHash = hash('sha256', json_encode($payload));

        foreach ($webhooks as $webhook) {
            $delivery = WebhookDelivery::create([
                'webhook_id' => $webhook->id,
                'event_id' => $payload['id'],
                'event_type' => $eventType,
                'payload_hash' => $payloadHash,
                'payload' => $payload,
                'attempt_number' => 1,
                'status' => WebhookDelivery::STATUS_PENDING,
                'scheduled_at' => now(),
                'created_at' => now(),
            ]);

            DeliverWebhook::dispatch($delivery, $payload);
        }
    }
}
