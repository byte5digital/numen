<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Space;
use App\Services\Performance\PerformanceIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PerformanceTrackingController extends Controller
{
    /** 1×1 transparent GIF (43 bytes). */
    private const PIXEL_GIF = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";

    public function __construct(
        private readonly PerformanceIngestService $ingestService,
    ) {}

    /**
     * GET /api/v1/spaces/{space}/tracking/pixel.gif
     *
     * Returns a 1×1 transparent GIF and logs a page_view event.
     */
    public function pixel(Request $request, Space $space): Response
    {
        $contentId = $request->query('cid', '');
        $sessionId = $request->query('sid', uniqid('px_'));
        $visitorId = $request->query('vid');

        if ($contentId !== '' && $contentId !== null) {
            try {
                $this->ingestService->ingestEvent([
                    'space_id' => $space->id,
                    'content_id' => $contentId,
                    'event_type' => 'page_view',
                    'source' => 'pixel',
                    'session_id' => (string) $sessionId,
                    'visitor_id' => $visitorId ? (string) $visitorId : null,
                    'occurred_at' => now()->toISOString(),
                ]);
            } catch (\Throwable) {
                // Pixel must always return the GIF — never fail visibly
            }
        }

        return response(self::PIXEL_GIF, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }

    /**
     * POST /api/v1/spaces/{space}/tracking/events
     *
     * Bulk JSON event intake.
     */
    public function events(Request $request, Space $space): JsonResponse
    {
        $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.content_id' => ['required', 'string'],
            'events.*.event_type' => ['required', 'string', 'in:page_view,click,scroll_depth,time_on_page,bounce,conversion,social_share,view,engagement'],
            'events.*.source' => ['nullable', 'string', 'in:pixel,webhook,api,sdk'],
            'events.*.value' => ['nullable', 'numeric'],
            'events.*.metadata' => ['nullable', 'array'],
            'events.*.session_id' => ['required', 'string'],
            'events.*.visitor_id' => ['nullable', 'string'],
            'events.*.occurred_at' => ['nullable', 'date'],
        ]);

        $eventPayloads = collect($request->input('events'))->map(function (array $event) use ($space) {
            return array_merge($event, [
                'space_id' => $space->id,
                'source' => $event['source'] ?? 'sdk',
            ]);
        })->all();

        $ingested = $this->ingestService->ingestBatch($eventPayloads);

        return response()->json([
            'ingested' => $ingested->count(),
        ], 202);
    }

    /**
     * POST /api/v1/track  (legacy single-event endpoint)
     *
     * Kept for backward compatibility with chunk 1.
     */
    public function track(Request $request): Response
    {
        $validated = $request->validate([
            'content_id' => ['required', 'string'],
            'event_type' => ['required', 'string', 'in:page_view,click,scroll_depth,time_on_page,bounce,conversion,social_share,view,engagement'],
            'source' => ['required', 'string', 'in:pixel,webhook,api,sdk'],
            'value' => ['nullable', 'numeric'],
            'metadata' => ['nullable', 'array'],
            'session_id' => ['required', 'string'],
            'visitor_id' => ['nullable', 'string'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $content = Content::find($validated['content_id']);

        if ($content === null) {
            abort(422, 'Content not found.');
        }

        $this->ingestService->ingestEvent(array_merge($validated, [
            'space_id' => $content->space_id,
        ]));

        return response()->noContent();
    }
}
