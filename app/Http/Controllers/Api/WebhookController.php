<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Rules\ExternalUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    /**
     * Reserved header names that must not be overridden by custom headers.
     */
    private const RESERVED_HEADERS = ['x-numen-signature', 'content-type', 'user-agent'];

    /**
     * List all webhooks for a space.
     *
     * GET /api/v1/webhooks?space_id=<required>
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string', 'exists:spaces,id'],
        ]);

        $webhooks = Webhook::where('space_id', $validated['space_id'])
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
            'url' => ['required', 'url', 'max:2048', new ExternalUrl],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'retry_policy' => ['sometimes', 'nullable', 'array'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'headers.*' => ['string', 'regex:/^[^\r\n]+$/'],
            'batch_mode' => ['sometimes', 'boolean'],
            'batch_timeout' => ['sometimes', 'integer', 'min:100', 'max:300000'],
        ]);

        if (isset($validated['headers'])) {
            $validated['headers'] = $this->sanitizeHeaders($validated['headers']);
        }

        $validated['secret'] = Str::random(64);

        $webhook = Webhook::create($validated);

        return response()->json(['data' => $this->format($webhook)], 201);
    }

    /**
     * Show a single webhook.
     *
     * GET /api/v1/webhooks/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);
        $this->authorizeSpaceAccess($request, $webhook);

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
        $this->authorizeSpaceAccess($request, $webhook);

        $validated = $request->validate([
            'url' => ['sometimes', 'url', 'max:2048', new ExternalUrl, Rule::unique('webhooks')->where('space_id', $webhook->space_id)->ignore($webhook->id)],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['required_with:events', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'retry_policy' => ['sometimes', 'nullable', 'array'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'headers.*' => ['string', 'regex:/^[^\r\n]+$/'],
            'batch_mode' => ['sometimes', 'boolean'],
            'batch_timeout' => ['sometimes', 'integer', 'min:100', 'max:300000'],
        ]);

        if (isset($validated['headers'])) {
            $validated['headers'] = $this->sanitizeHeaders($validated['headers']);
        }

        $webhook->update($validated);

        return response()->json(['data' => $this->format($webhook->fresh())]);
    }

    /**
     * Rotate the signing secret.
     *
     * POST /api/v1/webhooks/{id}/rotate-secret
     */
    public function rotateSecret(Request $request, string $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);
        $this->authorizeSpaceAccess($request, $webhook);

        $newSecret = Str::random(64);
        $webhook->update(['secret' => $newSecret]);

        return response()->json([
            'data' => [
                'id' => $webhook->id,
                'secret' => $newSecret,
            ],
        ]);
    }

    /**
     * Soft-delete a webhook.
     *
     * DELETE /api/v1/webhooks/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);
        $this->authorizeSpaceAccess($request, $webhook);

        $webhook->delete();

        return response()->json(null, 204);
    }

    /**
     * Verify the webhook belongs to a space the authenticated user is authorized to access.
     * Aborts with 403 if the request includes a space_id that does not match the webhook's space.
     */
    private function authorizeSpaceAccess(Request $request, Webhook $webhook): void
    {
        $requestedSpaceId = $request->input('space_id') ?? $request->query('space_id');

        if ($requestedSpaceId !== null && $requestedSpaceId !== $webhook->space_id) {
            abort(403, 'This webhook does not belong to the specified space.');
        }
    }

    /**
     * Remove reserved headers and validate header key format.
     * Keys must be alphanumeric with hyphens/underscores only.
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $key => $value) {
            // Reject invalid key format
            if (! preg_match('/^[a-zA-Z0-9_-]+$/', (string) $key)) {
                continue;
            }

            // Reject reserved headers (case-insensitive)
            if (in_array(strtolower((string) $key), self::RESERVED_HEADERS, true)) {
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
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
            'created_at' => $webhook->created_at->toIso8601String(),
            'updated_at' => $webhook->updated_at->toIso8601String(),
        ];
    }
}
