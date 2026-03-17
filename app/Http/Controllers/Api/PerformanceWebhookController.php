<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Services\Performance\PerformanceIngestService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PerformanceWebhookController extends Controller
{
    public function __construct(
        private readonly PerformanceIngestService $ingestService,
    ) {}

    /**
     * POST /api/v1/performance/webhook
     * Accepts external analytics payloads and maps them to internal events.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->all();

        $events = $this->mapPayload($payload);

        foreach ($events as $eventData) {
            $content = Content::find($eventData['content_id'] ?? null);
            if ($content === null) {
                continue;
            }

            $this->ingestService->ingestEvent(array_merge($eventData, [
                'space_id' => $content->space_id,
            ]));
        }

        return response()->noContent();
    }

    /**
     * Map external analytics formats to internal event format.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapPayload(array $payload): array
    {
        // Google Analytics / GA4-style batch
        if (isset($payload['events']) && is_array($payload['events'])) {
            return array_map(fn (array $e) => $this->mapGaEvent($e), $payload['events']);
        }

        // Segment-style single event
        if (isset($payload['event'], $payload['properties'])) {
            return [$this->mapSegmentEvent($payload)];
        }

        // Native format — pass through directly
        if (isset($payload['content_id'], $payload['event_type'])) {
            return [array_merge([
                'source' => 'webhook',
                'session_id' => $payload['session_id'] ?? $payload['anonymousId'] ?? uniqid('wh_'),
            ], $payload)];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function mapGaEvent(array $event): array
    {
        $params = $event['params'] ?? [];

        return [
            'content_id' => $params['content_id'] ?? $params['page_path'] ?? '',
            'event_type' => $this->resolveEventType($event['name'] ?? ''),
            'source' => 'webhook',
            'value' => $params['value'] ?? null,
            'metadata' => $params,
            'session_id' => $params['session_id'] ?? $event['client_id'] ?? uniqid('ga_'),
            'visitor_id' => $event['client_id'] ?? null,
            'occurred_at' => isset($params['timestamp']) ? date('Y-m-d H:i:s', (int) $params['timestamp']) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function mapSegmentEvent(array $event): array
    {
        $properties = $event['properties'] ?? [];

        return [
            'content_id' => $properties['content_id'] ?? $properties['path'] ?? '',
            'event_type' => $this->resolveEventType($event['event'] ?? ''),
            'source' => 'webhook',
            'value' => $properties['value'] ?? null,
            'metadata' => $properties,
            'session_id' => $event['anonymousId'] ?? $event['sessionId'] ?? uniqid('seg_'),
            'visitor_id' => $event['userId'] ?? $event['anonymousId'] ?? null,
            'occurred_at' => $event['timestamp'] ?? null,
        ];
    }

    private function resolveEventType(string $name): string
    {
        $map = [
            'page_view' => 'view',
            'page_viewed' => 'view',
            'pageview' => 'view',
            'scroll' => 'scroll_depth',
            'scroll_depth' => 'scroll_depth',
            'time_on_page' => 'time_on_page',
            'engagement' => 'engagement',
            'click' => 'engagement',
            'conversion' => 'conversion',
            'goal_completed' => 'conversion',
            'bounce' => 'bounce',
        ];

        return $map[strtolower($name)] ?? 'engagement';
    }
}
