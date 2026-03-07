<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    /**
     * List all webhooks for a space.
     *
     * GET /api/v1/webhooks
     */
    public function index(Request $request): JsonResponse
    {
        $spaceId = $request->query('space_id');

        $webhooks = Webhook::query()
            ->when($spaceId, fn ($q) => $q->where('space_id', $spaceId))
            ->latest()
            ->get()
            ->map(fn (Webhook $w) => $this->format($w));

        return response()->json(['data' => $webhooks]);
    }

    /**
     * Create a new webhook.
     *
     * POST /api/v1/webhooks
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string', 'exists:spaces,id'],
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'retry_policy' => ['sometimes', 'nullable', 'array'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'batch_mode' => ['sometimes', 'boolean'],
            'batch_timeout' => ['sometimes', 'integer', 'min:100', 'max:300000'],
        ]);

        $validated['secret'] = Str::random(64);

        $webhook = Webhook::create($validated);

        return response()->json(['data' => $this->format($webhook)], 201);
    }

    /**
     * Show a single webhook.
     *
     * GET /api/v1/webhooks/{id}
     */
    public function show(string $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        return response()->json(['data' => $this->format($webhook)]);
    }

    /**
     * Update a webhook.
     *
     * PUT /api/v1/webhooks/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        $validated = $request->validate([
            'url' => ['sometimes', 'url', 'max:2048', Rule::unique('webhooks')->where('space_id', $webhook->space_id)->ignore($webhook->id)],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['required_with:events', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'retry_policy' => ['sometimes', 'nullable', 'array'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'batch_mode' => ['sometimes', 'boolean'],
            'batch_timeout' => ['sometimes', 'integer', 'min:100', 'max:300000'],
        ]);

        $webhook->update($validated);

        return response()->json(['data' => $this->format($webhook->fresh())]);
    }

    /**
     * Rotate the signing secret.
     *
     * POST /api/v1/webhooks/{id}/rotate-secret
     */
    public function rotateSecret(string $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);
        $webhook->update(['secret' => Str::random(64)]);

        return response()->json([
            'data' => [
                'id' => $webhook->id,
                'secret' => $webhook->secret,
            ],
        ]);
    }

    /**
     * Soft-delete a webhook.
     *
     * DELETE /api/v1/webhooks/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        Webhook::findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    /**
     * List delivery attempts for a webhook.
     *
     * GET /api/v1/webhooks/{id}/deliveries
     */
    public function deliveries(Request $request, string $id): JsonResponse
    {
        Webhook::findOrFail($id); // 404 if not found

        $query = WebhookDelivery::where('webhook_id', $id)->latest('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $deliveries = $query->limit(100)->get();

        return response()->json(['data' => $deliveries]);
    }

    private function format(Webhook $webhook): array
    {
        return [
            'id' => $webhook->id,
            'space_id' => $webhook->space_id,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'is_active' => $webhook->is_active,
            'retry_policy' => $webhook->retry_policy,
            'headers' => $webhook->headers,
            'batch_mode' => $webhook->batch_mode,
            'batch_timeout' => $webhook->batch_timeout,
            'created_at' => $webhook->created_at?->toIso8601String(),
            'updated_at' => $webhook->updated_at?->toIso8601String(),
        ];
    }
}
