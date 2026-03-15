<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Persona;
use App\Models\RepurposedContent;
use App\Models\Space;
use App\Services\AuthorizationService;
use App\Services\FormatAdapterService;
use App\Services\RepurposingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class RepurposingController extends Controller
{
    public function __construct(
        private readonly RepurposingService $service,
        private readonly AuthorizationService $authz,
        private readonly FormatAdapterService $formats,
    ) {}

    /**
     * List all repurposed versions for a given content item.
     *
     * GET /v1/content/{content}/repurposed
     */
    public function index(Request $request, Content $content): JsonResponse
    {
        // Verify the authenticated user has read access to this content's space.
        $this->authz->authorize($request->user(), 'content.read', $content->space_id);

        $results = $this->service->getResults($content);

        return response()->json(['data' => $results]);
    }

    /**
     * Trigger repurposing for a single content item.
     *
     * POST /v1/content/{content}/repurpose
     * Body: { format_key, persona_id? }
     */
    public function store(Request $request, Content $content): JsonResponse
    {
        // Verify the authenticated user can update content in this space.
        $this->authz->authorize($request->user(), 'content.update', $content->space_id);

        $supportedFormats = array_keys($this->formats->getSupportedFormats());

        $validated = $request->validate([
            'format_key' => ['required', 'string', Rule::in($supportedFormats)],
            'persona_id' => ['nullable', 'integer', Rule::exists('personas', 'id')->where('space_id', $content->space_id)],
        ]);

        $persona = isset($validated['persona_id'])
            ? Persona::find($validated['persona_id'])
            : null;

        $item = $this->service->repurpose($content, $validated['format_key'], $persona);

        return response()->json(['data' => $item], 202);
    }

    /**
     * Get a single repurposed content item (for polling status).
     *
     * GET /v1/repurposed/{repurposedContent}
     */
    public function show(Request $request, RepurposedContent $repurposedContent): JsonResponse
    {
        // Verify the authenticated user has read access to the space this belongs to.
        $this->authz->authorize($request->user(), 'content.read', $repurposedContent->space_id);

        $repurposedContent->refresh();

        return response()->json(['data' => $repurposedContent]);
    }

    /**
     * Estimate the cost of repurposing all published content in a space.
     *
     * GET /v1/spaces/{space}/repurpose/estimate?format_key=...
     */
    public function estimateCost(Request $request, Space $space): JsonResponse
    {
        // Verify the authenticated user has read access to this space.
        $this->authz->authorize($request->user(), 'content.read', $space->id);

        $supportedFormats = array_keys($this->formats->getSupportedFormats());

        $validated = $request->validate([
            'format_key' => ['required', 'string', Rule::in($supportedFormats)],
        ]);

        $estimate = $this->service->estimateCost($space, $validated['format_key']);

        return response()->json(['data' => $estimate]);
    }

    /**
     * Trigger batch repurposing for all published content in a space.
     *
     * POST /v1/spaces/{space}/repurpose/batch
     * Body: { format_key, persona_id? }
     * Enforces 50-item limit — returns 422 with cost estimate if exceeded.
     */
    public function batch(Request $request, Space $space): JsonResponse
    {
        // Verify the authenticated user can update content in this space.
        $this->authz->authorize($request->user(), 'content.update', $space->id);

        $supportedFormats = array_keys($this->formats->getSupportedFormats());

        $validated = $request->validate([
            'format_key' => ['required', 'string', Rule::in($supportedFormats)],
            'persona_id' => ['nullable', 'integer', Rule::exists('personas', 'id')->where('space_id', $space->id)],
        ]);

        $persona = isset($validated['persona_id'])
            ? Persona::find($validated['persona_id'])
            : null;

        try {
            $batch = $this->service->repurposeBatch($space, $validated['format_key'], $persona);
        } catch (RuntimeException $e) {
            $estimate = $this->service->estimateCost($space, $validated['format_key']);

            return response()->json([
                'message' => $e->getMessage(),
                'estimate' => $estimate,
            ], 422);
        }

        return response()->json(['data' => $batch], 202);
    }
}
