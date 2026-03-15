<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Rules\ExternalUrl;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WebhookAdminController extends Controller
{
    /**
     * Reserved header names that must not be overridden by custom headers.
     */
    private const RESERVED_HEADERS = ['x-numen-signature', 'content-type', 'user-agent'];

    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * List all webhooks for the user's current space.
     */
    public function index(Request $request): Response
    {
        $spaceId = $request->user()->currentSpace?->id;

        if (! $spaceId) {
            abort(403, 'No active space selected.');
        }

        $this->authz->authorize($request->user(), 'webhooks.manage', $spaceId);

        $webhooks = Webhook::where('space_id', $spaceId)
            ->latest()
            ->get()
            ->map(fn (Webhook $w) => [
                'id' => $w->id,
                'url' => $w->url,
                'events' => $w->events,
                'is_active' => $w->is_active,
                'created_at' => $w->created_at->toIso8601String(),
            ]);

        return Inertia::render('Settings/Webhooks', [
            'webhooks' => $webhooks,
            'newSecret' => session('newSecret'),
        ]);
    }

    /**
     * Create a new webhook.
     */
    public function store(Request $request): RedirectResponse
    {
        $spaceId = $request->user()->currentSpace?->id;

        if (! $spaceId) {
            abort(403, 'No active space selected.');
        }

        $this->authz->authorize($request->user(), 'webhooks.manage', $spaceId);

        $validated = $request->validate([
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

        $validated['space_id'] = $spaceId;
        $validated['secret'] = Str::random(64);

        Webhook::create($validated);

        return redirect()->route('admin.webhooks')->with('success', 'Webhook created.');
    }

    /**
     * Update a webhook.
     */
    public function update(Request $request, string $id): RedirectResponse
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

        return redirect()->route('admin.webhooks')->with('success', 'Webhook updated.');
    }

    /**
     * Soft-delete a webhook.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $webhook = Webhook::findOrFail($id);
        $this->authorizeSpaceAccess($request, $webhook);

        $webhook->delete();

        return redirect()->route('admin.webhooks')->with('success', 'Webhook deleted.');
    }

    /**
     * Rotate the signing secret.
     */
    public function rotateSecret(Request $request, string $id): RedirectResponse
    {
        $webhook = Webhook::findOrFail($id);
        $this->authorizeSpaceAccess($request, $webhook);

        $newSecret = Str::random(64);
        $webhook->update(['secret' => $newSecret]);

        return redirect()->route('admin.webhooks')->with('newSecret', $newSecret);
    }

    /**
     * Return last 50 deliveries for a webhook as JSON.
     */
    public function deliveries(Request $request, string $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);
        $this->authorizeSpaceAccess($request, $webhook);

        $deliveries = WebhookDelivery::where('webhook_id', $id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (WebhookDelivery $d) => [
                'id' => $d->id,
                'event_type' => $d->event_type,
                'status' => $d->status,
                'http_status' => $d->http_status,
                'attempt_number' => $d->attempt_number,
                'created_at' => $d->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $deliveries]);
    }

    /**
     * Re-queue a failed delivery.
     */
    public function redeliver(Request $request, string $id, string $deliveryId): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);
        $this->authorizeSpaceAccess($request, $webhook);

        $delivery = WebhookDelivery::where('webhook_id', $id)
            ->where('id', $deliveryId)
            ->firstOrFail();

        // Mark as pending to re-queue
        $delivery->update([
            'status' => WebhookDelivery::STATUS_PENDING,
            'scheduled_at' => now(),
        ]);

        return response()->json(['message' => 'Delivery re-queued for delivery.']);
    }

    /**
     * Verify the webhook belongs to a space the authenticated user is authorized to access.
     *
     * @throws \App\Exceptions\PermissionDeniedException
     */
    private function authorizeSpaceAccess(Request $request, Webhook $webhook): void
    {
        $this->authz->authorize($request->user(), 'webhooks.manage', $webhook->space_id);
    }

    /**
     * Remove reserved headers and validate header key format.
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
}
