<?php

declare(strict_types=1);

namespace App\Services\Migration;

/**
 * Combines schema inspection + AI field mapping into a preview payload
 * for the migration wizard UI.
 */
class MappingPreviewService
{
    public function __construct(
        private readonly SchemaInspectorService $schemaInspector,
        private readonly AiFieldMappingService $aiMapper,
    ) {}

    /**
     * Generate a full mapping preview for a migration session.
     *
     * @param  list<array{key: string, label: string, fields: list<array{name: string, type: string, required: bool}>}>  $sourceSchema
     * @param  string|null  $spaceId  Numen space to introspect (null = all)
     * @return array{
     *   comparison: array{matched_types: list<array{source: string, numen: string, field_overlap: int, field_total: int}>, unmatched_source: list<string>, unmatched_numen: list<string>},
     *   type_mappings: list<array{source_type: string, numen_type: string|null, mappings: list<array{source_field: string, target_field: string|null, source_type: string, target_type: string|null, confidence: float, requires_transform: bool}>}>,
     *   summary: array{total_source_types: int, mapped_types: int, total_fields: int, mapped_fields: int, avg_confidence: float}
     * }
     */
    public function generatePreview(array $sourceSchema, ?string $spaceId = null): array
    {
        $numenSchema = $this->schemaInspector->inspectNumenSchema($spaceId);
        $comparison = $this->schemaInspector->compareSchemas($sourceSchema, $numenSchema);
        $typeMappings = $this->aiMapper->suggestAll($sourceSchema, $numenSchema);
        $summary = $this->computeSummary($typeMappings);

        return [
            'comparison' => $comparison,
            'type_mappings' => $typeMappings,
            'summary' => $summary,
        ];
    }

    /**
     * Generate preview using pre-fetched Numen schema (for testing or caching).
     *
     * @param  list<array{key: string, label: string, fields: list<array{name: string, type: string, required: bool}>}>  $sourceSchema
     * @param  list<array{key: string, label: string, fields: list<array{name: string, type: string, required: bool}>}>  $numenSchema
     * @return array{
     *   comparison: array{matched_types: list<array{source: string, numen: string, field_overlap: int, field_total: int}>, unmatched_source: list<string>, unmatched_numen: list<string>},
     *   type_mappings: list<array{source_type: string, numen_type: string|null, mappings: list<array{source_field: string, target_field: string|null, source_type: string, target_type: string|null, confidence: float, requires_transform: bool}>}>,
     *   summary: array{total_source_types: int, mapped_types: int, total_fields: int, mapped_fields: int, avg_confidence: float}
     * }
     */
    public function generatePreviewWithSchema(array $sourceSchema, array $numenSchema): array
    {
        $comparison = $this->schemaInspector->compareSchemas($sourceSchema, $numenSchema);
        $typeMappings = $this->aiMapper->suggestAll($sourceSchema, $numenSchema);
        $summary = $this->computeSummary($typeMappings);

        return [
            'comparison' => $comparison,
            'type_mappings' => $typeMappings,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{total_source_types: int, mapped_types: int, total_fields: int, mapped_fields: int, avg_confidence: float}
     */
    private function computeSummary(array $typeMappings): array
    {
        $totalTypes = count($typeMappings);
        $mappedTypes = 0;
        $totalFields = 0;
        $mappedFields = 0;
        $confidenceSum = 0.0;

        foreach ($typeMappings as $tm) {
            if ($tm['numen_type'] !== null) {
                $mappedTypes++;
            }

            foreach ($tm['mappings'] as $fm) {
                $totalFields++;
                if ($fm['target_field'] !== null) {
                    $mappedFields++;
                    $confidenceSum += $fm['confidence'];
                }
            }
        }

        return [
            'total_source_types' => $totalTypes,
            'mapped_types' => $mappedTypes,
            'total_fields' => $totalFields,
            'mapped_fields' => $mappedFields,
            'avg_confidence' => $mappedFields > 0 ? round($confidenceSum / $mappedFields, 2) : 0.0,
        ];
    }
}
