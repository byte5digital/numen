<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Migration;

use App\Http\Controllers\Controller;
use App\Models\Migration\MigrationSession;
use App\Models\Space;
use App\Services\Migration\CmsConnectorFactory;
use App\Services\Migration\SchemaInspectorService;
use Illuminate\Http\JsonResponse;

class MigrationSchemaController extends Controller
{
    public function __construct(
        private readonly CmsConnectorFactory $connectorFactory,
        private readonly SchemaInspectorService $schemaInspector,
    ) {}

    public function show(Space $space, string $migrationId): JsonResponse
    {
        $session = MigrationSession::findOrFail($migrationId);
        abort_unless($session->space_id === $space->id, 404);

        $credentials = is_array($session->credentials)
            ? $session->credentials
            : (is_string($session->credentials) ? json_decode($session->credentials, true) : null);

        $connector = $this->connectorFactory->make(
            $session->source_cms,
            $session->source_url,
            $credentials,
        );

        $schema = $this->schemaInspector->inspectSchema($connector);

        $session->update(['schema_snapshot' => $schema]);

        return response()->json(['data' => $schema]);
    }

    public function compare(Space $space, string $migrationId): JsonResponse
    {
        $session = MigrationSession::findOrFail($migrationId);
        abort_unless($session->space_id === $space->id, 404);

        $sourceSchema = $session->schema_snapshot;

        if (empty($sourceSchema)) {
            $credentials = is_array($session->credentials)
                ? $session->credentials
                : (is_string($session->credentials) ? json_decode($session->credentials, true) : null);

            $connector = $this->connectorFactory->make(
                $session->source_cms,
                $session->source_url,
                $credentials,
            );
            $sourceSchema = $this->schemaInspector->inspectSchema($connector);
            $session->update(['schema_snapshot' => $sourceSchema]);
        }

        $numenSchema = $this->schemaInspector->inspectNumenSchema($space->id);
        $comparison = $this->schemaInspector->compareSchemas($sourceSchema, $numenSchema);

        return response()->json([
            'data' => [
                'source_schema' => $sourceSchema,
                'numen_schema' => $numenSchema,
                'comparison' => $comparison,
            ],
        ]);
    }
}
