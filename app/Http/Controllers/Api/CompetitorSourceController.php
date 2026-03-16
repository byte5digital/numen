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
        $source = CompetitorSource::create($request->validated());

        return response()->json(['data' => new CompetitorSourceResource($source)], 201);
    }

    /**
     * GET /api/v1/competitor/sources/{id}
     */
    public function show(string $id): JsonResponse
    {
        $source = CompetitorSource::findOrFail($id);

        return response()->json(['data' => new CompetitorSourceResource($source)]);
    }

    /**
     * PATCH /api/v1/competitor/sources/{id}
     */
    public function update(UpdateCompetitorSourceRequest $request, string $id): JsonResponse
    {
        $source = CompetitorSource::findOrFail($id);
        $source->update($request->validated());

        return response()->json(['data' => new CompetitorSourceResource($source)]);
    }

    /**
     * DELETE /api/v1/competitor/sources/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $source = CompetitorSource::findOrFail($id);
        $source->delete();

        return response()->json(null, 204);
    }
}
