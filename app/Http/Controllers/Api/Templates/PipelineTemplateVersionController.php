<?php

namespace App\Http\Controllers\Api\Templates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\CreateVersionRequest;
use App\Http\Resources\PipelineTemplateVersionResource;
use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateVersion;
use App\Services\PipelineTemplates\PipelineTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PipelineTemplateVersionController extends Controller
{
    public function __construct(
        private readonly PipelineTemplateService $service,
    ) {}

    public function index(PipelineTemplate $template): AnonymousResourceCollection
    {
        return PipelineTemplateVersionResource::collection($template->versions()->latest()->get());
    }

    public function store(PipelineTemplate $template, CreateVersionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $version = $this->service->createVersion($template, $data['definition'], $data['version'], $data['changelog'] ?? null);

        return (new PipelineTemplateVersionResource($version))->response()->setStatusCode(201);
    }

    public function show(PipelineTemplate $template, PipelineTemplateVersion $version): PipelineTemplateVersionResource
    {
        return new PipelineTemplateVersionResource($version);
    }
}
