<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompetitorSourceRequest;
use App\Http\Requests\UpdateCompetitorSourceRequest;
use App\Http\Resources\CompetitorSourceResource;
use App\Models\CompetitorSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompetitorSourceController extends Controller
{
    /**
     * GET /api/v1/competitor/sources
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $currentSpace = app()->bound('current_space') ? app('current_space') : null;
        abort_if($currentSpace && $validated['space_id'] !== $currentSpace->id, 403);

        $sources = CompetitorSource::where('space_id', $validated['space_id'])
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return CompetitorSourceResource::collection($sources);
    }

    /**
     * POST /api/v1/competitor/sources
     */
    public function store(StoreCompetitorSourceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $spaceId = $validated['space_id'];

        $currentSpace = app()->bound('current_space') ? app('current_space') : null;
        abort_if($currentSpace && $spaceId !== $currentSpace->id, 403);

        $count = CompetitorSource::where('space_id', $spaceId)->count();
        abort_if($count >= 50, 422, 'Maximum 50 competitor sources per space');

        $source = CompetitorSource::create($validated);

        return response()->json(['data' => new CompetitorSourceResource($source)], 201);
    }

    /**
     * GET /api/v1/competitor/sources/{id}
     */
    public function show(string $id): JsonResponse
    {
        $source = CompetitorSource::findOrFail($id);

        $currentSpace = app()->bound('current_space') ? app('current_space') : null;
        abort_if($currentSpace && $source->space_id !== $currentSpace->id, 403);

        return response()->json(['data' => new CompetitorSourceResource($source)]);
    }

    /**
     * PATCH /api/v1/competitor/sources/{id}
     */
    public function update(UpdateCompetitorSourceRequest $request, string $id): JsonResponse
    {
        $source = CompetitorSource::findOrFail($id);

        $currentSpace = app()->bound('current_space') ? app('current_space') : null;
        abort_if($currentSpace && $source->space_id !== $currentSpace->id, 403);

        $source->update($request->validated());

        return response()->json(['data' => new CompetitorSourceResource($source)]);
    }

    /**
     * DELETE /api/v1/competitor/sources/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $source = CompetitorSource::findOrFail($id);

        $currentSpace = app()->bound('current_space') ? app('current_space') : null;
        abort_if($currentSpace && $source->space_id !== $currentSpace->id, 403);

        $source->delete();

        return response()->json(null, 204);
    }
}
