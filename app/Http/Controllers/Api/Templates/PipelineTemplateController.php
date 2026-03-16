<?php

namespace App\Http\Controllers\Api\Templates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\StorePipelineTemplateRequest;
use App\Http\Requests\Templates\UpdatePipelineTemplateRequest;
use App\Http\Resources\PipelineTemplateResource;
use App\Models\PipelineTemplate;
use App\Models\Space;
use App\Services\PipelineTemplates\PipelineTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PipelineTemplateController extends Controller
{
    public function __construct(
        private readonly PipelineTemplateService $service,
    ) {}

    public function index(Space $space): AnonymousResourceCollection
    {
        $spaceTemplates = PipelineTemplate::with('latestVersion')
            ->where('space_id', $space->id)
            ->latest()
            ->get();

        $marketplace = PipelineTemplate::with('latestVersion')
            ->whereNull('space_id')
            ->where('is_published', true)
            ->latest()
            ->get();

        return PipelineTemplateResource::collection($spaceTemplates->merge($marketplace));
    }

    public function show(Space $space, PipelineTemplate $template): PipelineTemplateResource
    {
        abort_if($template->space_id !== null && $template->space_id !== $space->id, 403);

        $template->load('latestVersion', 'versions');

        return new PipelineTemplateResource($template);
    }

    public function store(Space $space, StorePipelineTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $template = $this->service->create($space, $data);

        if (! empty($data['definition'])) {
            $this->service->createVersion($template, $data['definition'], $data['version'] ?? '1.0.0', $data['changelog'] ?? null);
        }

        $template->load('latestVersion');

        return (new PipelineTemplateResource($template))->response()->setStatusCode(201);
    }

    public function update(Space $space, PipelineTemplate $template, UpdatePipelineTemplateRequest $request): PipelineTemplateResource
    {
        abort_if($template->space_id !== null && $template->space_id !== $space->id, 403);

        $template = $this->service->update($template, $request->validated());
        $template->load('latestVersion');

        return new PipelineTemplateResource($template);
    }

    public function destroy(Space $space, PipelineTemplate $template): JsonResponse
    {
        abort_if($template->space_id !== null && $template->space_id !== $space->id, 403);

        $this->service->delete($template);

        return response()->json(null, 204);
    }

    public function publish(Space $space, PipelineTemplate $template): PipelineTemplateResource
    {
        abort_if($template->space_id !== null && $template->space_id !== $space->id, 403);

        $this->service->publish($template);

        return new PipelineTemplateResource($template->refresh());
    }

    public function unpublish(Space $space, PipelineTemplate $template): PipelineTemplateResource
    {
        abort_if($template->space_id !== null && $template->space_id !== $space->id, 403);

        $this->service->unpublish($template);

        return new PipelineTemplateResource($template->refresh());
    }
}
