<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormatTemplate;
use App\Services\AuthorizationService;
use App\Services\FormatAdapterService;
use App\Services\FormatTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormatTemplateController extends Controller
{
    public function __construct(
        private readonly FormatTemplateService $service,
        private readonly AuthorizationService $authz,
    ) {}

    /**
     * List all templates for the authenticated space.
     * Includes global defaults merged with space overrides.
     *
     * GET /v1/format-templates
     */
    public function index(Request $request): JsonResponse
    {
        $spaceId = $request->user()->current_space_id
            ?? $request->input('space_id');

        // Require at minimum read access to this space.
        $this->authz->authorize($request->user(), 'content.read', (string) $spaceId);

        $templates = $this->service->getAllForSpace((int) $spaceId);

        return response()->json(['data' => $templates]);
    }

    /**
     * Create a custom template for the authenticated space.
     *
     * POST /v1/format-templates
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'integer', 'exists:spaces,id'],
            'format_key' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'system_prompt' => ['required', 'string'],
            'user_prompt_template' => ['required', 'string'],
            'output_schema' => ['nullable', 'array'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:8000'],
            'is_active' => ['boolean'],
        ]);

        // Verify the authenticated user has content management rights for that space.
        $this->authz->authorize($request->user(), 'content.update', (string) $validated['space_id']);

        $template = $this->service->createTemplate($validated);

        return response()->json(['data' => $template], 201);
    }

    /**
     * Update an existing template.
     *
     * PATCH /v1/format-templates/{template}
     */
    public function update(Request $request, FormatTemplate $template): JsonResponse
    {
        // Global templates (space_id IS NULL) are read-only for regular users.
        if ($template->space_id === null) {
            return response()->json(['message' => 'Global default templates cannot be modified. Create a space-specific override instead.'], 403);
        }

        // Verify the authenticated user can manage content in the template's space.
        $this->authz->authorize($request->user(), 'content.update', (string) $template->space_id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'system_prompt' => ['sometimes', 'string'],
            'user_prompt_template' => ['sometimes', 'string'],
            'output_schema' => ['nullable', 'array'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:8000'],
            'is_active' => ['boolean'],
        ]);

        $template = $this->service->updateTemplate($template, $validated);

        return response()->json(['data' => $template]);
    }

    /**
     * Delete a space-specific template.
     * Global/default templates cannot be deleted.
     *
     * DELETE /v1/format-templates/{template}
     */
    public function destroy(Request $request, FormatTemplate $template): JsonResponse
    {
        if ($template->space_id === null) {
            return response()->json(['message' => 'Global default templates cannot be deleted.'], 403);
        }

        // Verify the authenticated user can manage content in the template's space.
        $this->authz->authorize($request->user(), 'content.update', (string) $template->space_id);

        $this->service->deleteTemplate($template);

        return response()->json(null, 204);
    }

    /**
     * List all 8 supported format keys with labels. No auth required.
     *
     * GET /v1/format-templates/supported
     */
    public function supported(Request $request): JsonResponse
    {
        $adapter = app(FormatAdapterService::class);
        $formats = $adapter->getSupportedFormats();

        $data = collect($formats)->map(function (array $meta, string $key) {
            return [
                'format_key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'] ?? null,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }
}
