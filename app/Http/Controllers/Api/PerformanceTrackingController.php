<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackEventRequest;
use App\Models\Content;
use App\Services\Performance\PerformanceIngestService;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PerformanceTrackingController extends Controller
{
    public function __construct(
        private readonly PerformanceIngestService $ingestService,
    ) {}

    /**
     * POST /api/v1/track
     * Public endpoint — rate limited 120/min via route definition.
     */
    public function track(TrackEventRequest $request): Response
    {
        $validated = $request->validated();

        /** @var Content|null $content */
        $content = Content::find($validated['content_id']);

        if ($content === null) {
            throw ValidationException::withMessages([
                'content_id' => ['Content not found.'],
            ]);
        }

        $this->ingestService->ingestEvent(array_merge($validated, [
            'space_id' => $content->space_id,
        ]));

        return response()->noContent();
    }
}
