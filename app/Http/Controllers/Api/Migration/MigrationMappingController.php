<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Migration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Migration\MigrationMappingRequest;
use App\Http\Resources\Migration\MigrationTypeMappingResource;
use App\Models\Migration\MigrationSession;
use App\Models\Migration\MigrationTypeMapping;
use App\Models\Space;
use App\Services\Migration\MappingPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MigrationMappingController extends Controller
{
    public function __construct(
        private readonly MappingPreviewService $previewService,
    ) {}

    public function index(Space $space, string $migrationId): AnonymousResourceCollection
    {
        $session = MigrationSession::findOrFail($migrationId);
        abort_unless($session->space_id === $space->id, 404);

        $mappings = MigrationTypeMapping::where('migration_session_id', $session->id)
            ->orderBy('source_type_key')
            ->get();

        return MigrationTypeMappingResource::collection($mappings);
    }

    public function suggest(Space $space, string $migrationId): JsonResponse
    {
        $session = MigrationSession::findOrFail($migrationId);
        abort_unless($session->space_id === $space->id, 404);

        $sourceSchema = $session->schema_snapshot;

        if (empty($sourceSchema)) {
            return response()->json([
                'message' => 'Source schema not yet fetched. Call GET .../schema first.',
            ], 422);
        }

        $preview = $this->previewService->generatePreview($sourceSchema, $space->id);

        return response()->json(['data' => $preview]);
    }

    public function store(MigrationMappingRequest $request, Space $space, string $migrationId): AnonymousResourceCollection
    {
        $session = MigrationSession::findOrFail($migrationId);
        abort_unless($session->space_id === $space->id, 404);

        $validated = $request->validated();

        $created = collect($validated['mappings'])->map(function (array $mapping) use ($session, $space) {
            return MigrationTypeMapping::updateOrCreate(
                [
                    'migration_session_id' => $session->id,
                    'source_type_key' => $mapping['source_type_key'],
                ],
                [
                    'space_id' => $space->id,
                    'source_type_label' => $mapping['source_type_label'] ?? null,
                    'numen_content_type_id' => $mapping['numen_content_type_id'] ?? null,
                    'numen_type_slug' => $mapping['numen_type_slug'] ?? null,
                    'field_map' => $mapping['field_map'],
                    'status' => $mapping['status'] ?? 'pending',
                ],
            );
        });

        return MigrationTypeMappingResource::collection($created);
    }

    public function preview(Space $space, string $migrationId): JsonResponse
    {
        $session = MigrationSession::findOrFail($migrationId);
        abort_unless($session->space_id === $space->id, 404);

        $sourceSchema = $session->schema_snapshot;

        if (empty($sourceSchema)) {
            return response()->json([
                'message' => 'Source schema not yet fetched. Call GET .../schema first.',
            ], 422);
        }

        $preview = $this->previewService->generatePreview($sourceSchema, $space->id);

        return response()->json(['data' => $preview]);
    }
}
