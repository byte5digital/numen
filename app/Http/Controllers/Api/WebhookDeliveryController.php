<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;

class WebhookDeliveryController extends Controller
{
    /**
     * List delivery history for a webhook.
     *
     * GET /api/v1/webhooks/{id}/deliveries
     */
    public function index(string $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        $deliveries = WebhookDelivery::where('webhook_id', $webhook->id)
            ->latest('created_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $deliveries]);
    }

    /**
     * Show a single delivery.
     *
     * GET /api/v1/webhooks/{id}/deliveries/{deliveryId}
     */
    public function show(string $id, string $deliveryId): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        $delivery = WebhookDelivery::where('webhook_id', $webhook->id)
            ->findOrFail($deliveryId);

        return response()->json(['data' => $delivery]);
    }

    /**
     * Retry a failed or abandoned delivery.
     *
     * POST /api/v1/webhooks/{id}/deliveries/{deliveryId}/redeliver
     */
    public function redeliver(string $id, string $deliveryId): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        $original = WebhookDelivery::where('webhook_id', $webhook->id)
            ->findOrFail($deliveryId);

        $payload = $original->payload ?? [];

        $redelivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_id' => $original->event_id,
            'event_type' => $original->event_type,
            'payload_hash' => $original->payload_hash,
            'payload' => $payload,
            'attempt_number' => $original->attempt_number + 1,
            'status' => WebhookDelivery::STATUS_PENDING,
            'scheduled_at' => now(),
            'created_at' => now(),
        ]);

        DeliverWebhook::dispatch($redelivery, $payload);

        return response()->json(['data' => $redelivery], 202);
    }
}
