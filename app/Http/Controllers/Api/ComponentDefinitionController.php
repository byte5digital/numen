<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComponentDefinition;
use App\Models\ContentBlock;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Dynamic Component Engine API
 *
 * Allows AI agents to register new reusable block types at runtime.
 * These types become available for both page_components and content_blocks.
 *
 * POST /api/v1/component-types       — register a new type (auth required)
 * GET  /api/v1/component-types       — list all types (builtin + custom)
 * GET  /api/v1/component-types/{type} — get single type definition
 */
class ComponentDefinitionController extends Controller
{
    public function __construct(private AuthorizationService $authz) {}

    public function index(): JsonResponse
    {
        $custom = ComponentDefinition::all()->keyBy('type');

        // Merge builtin content block types
        $builtin = collect(ContentBlock::builtinTypes())->map(function ($schema, $type) {
            return [
                'type' => $type,
                'label' => ucwords(str_replace('_', ' ', $type)),
                'description' => null,
                'schema' => $schema,
                'vue_template' => null,
                'is_builtin' => true,
                'created_by' => 'system',
            ];
        });

        $allTypes = $builtin->merge(
            $custom->map(fn ($def) => $def->toArray())
        )->values();

        return response()->json(['data' => $allTypes]);
    }

    public function show(string $type): JsonResponse
    {
        // Check builtin first
        $builtins = ContentBlock::builtinTypes();
        if (isset($builtins[$type])) {
            return response()->json(['data' => [
                'type' => $type,
                'label' => ucwords(str_replace('_', ' ', $type)),
                'schema' => $builtins[$type],
                'vue_template' => null,
                'is_builtin' => true,
                'created_by' => 'system',
            ]]);
        }

        $definition = ComponentDefinition::where('type', $type)->firstOrFail();

        return response()->json(['data' => $definition]);
    }

    /**
     * AI agents call this to register a brand-new component type.
     *
     * Required fields:
     *   type        — snake_case identifier (unique)
     *   label       — human-readable name
     *   schema      — field definitions: { fieldName: "string|text|number|array:col1,col2" }
     *
     * Optional:
     *   description  — when/why to use this block
     *   vue_template — raw HTML template with {{ field }} interpolations
     *   created_by   — defaults to 'ai_agent'
     */
    public function store(Request $request): JsonResponse
    {
        // Require component.manage permission — guards against unauthorized component registration
        $this->authz->authorize($request->user(), 'component.manage');

        $data = $request->validate([
            'type' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:component_definitions,type'],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'schema' => ['required', 'array'],
            'vue_template' => ['nullable', 'string'],
            'created_by' => ['nullable', 'string', 'in:human,ai_agent'],
        ]);

        // Validate that type doesn't conflict with builtins
        if (isset(ContentBlock::builtinTypes()[$data['type']])) {
            throw ValidationException::withMessages([
                'type' => ['This type conflicts with a builtin block type.'],
            ]);
        }

        $definition = ComponentDefinition::create([
            'type' => $data['type'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'schema' => $data['schema'],
            'vue_template' => $data['vue_template'] ?? null,
            'is_builtin' => false,
            'created_by' => $data['created_by'] ?? 'ai_agent',
        ]);

        // Auto-generate a default template if none was supplied
        if (! $definition->vue_template) {
            $definition->update([
                'vue_template' => $definition->generateDefaultTemplate(),
            ]);
        }

        // Audit log the creation
        $this->authz->log($request->user(), 'component.create', $definition, [
            'type' => $definition->type,
            'has_template' => (bool) $definition->vue_template,
            'schema_keys' => array_keys($definition->schema ?? []),
        ]);

        return response()->json(['data' => $definition->fresh()], 201);
    }

    /**
     * Update an existing custom component type (e.g. refine schema or template).
     */
    public function update(Request $request, string $type): JsonResponse
    {
        // Require component.manage permission
        $this->authz->authorize($request->user(), 'component.manage');

        $definition = ComponentDefinition::where('type', $type)->firstOrFail();

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'schema' => ['sometimes', 'array'],
            'vue_template' => ['nullable', 'string'],
        ]);

        $oldValues = $definition->only(array_keys($data));

        $definition->update($data);

        // Audit log the update
        $this->authz->log($request->user(), 'component.update', $definition, [
            'type' => $type,
            'changed_fields' => array_keys($data),
            'old_values' => $oldValues,
        ]);

        return response()->json(['data' => $definition->fresh()]);
    }
}
