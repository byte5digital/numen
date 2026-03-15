<?php

namespace App\Http\Controllers\Api\Templates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\InstallTemplateRequest;
use App\Http\Resources\PipelineTemplateInstallResource;
use App\Models\PipelineTemplateInstall;
use App\Models\PipelineTemplateVersion;
use App\Models\Space;
use App\Services\PipelineTemplates\PipelineTemplateInstallService;
use Illuminate\Http\JsonResponse;

class PipelineTemplateInstallController extends Controller
{
    public function __construct(
        private readonly PipelineTemplateInstallService $installService,
    ) {}

    public function store(PipelineTemplateVersion $version, Space $space, InstallTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $install = $this->installService->install($version, $space, $data['variable_values'] ?? [], $data['config_overrides'] ?? []);
        $install->load('template', 'templateVersion');

        return (new PipelineTemplateInstallResource($install))->response()->setStatusCode(201);
    }

    public function destroy(PipelineTemplateInstall $install): JsonResponse
    {
        $this->installService->uninstall($install);

        return response()->json(null, 204);
    }

    public function update(PipelineTemplateInstall $install, InstallTemplateRequest $request): PipelineTemplateInstallResource
    {
        $install->loadMissing('templateVersion.template');
        /** @var PipelineTemplateVersion $currentVersion */
        $currentVersion = $install->getRelation('templateVersion');
        /** @var \App\Models\PipelineTemplate $tmpl */
        $tmpl = $currentVersion->getRelation('template');
        /** @var PipelineTemplateVersion $newVersion */
        $newVersion = $tmpl->versions()->where('is_latest', true)->firstOrFail();
        $updatedInstall = $this->installService->update($install, $newVersion);
        $updatedInstall->load('template', 'templateVersion');

        return new PipelineTemplateInstallResource($updatedInstall);
    }
}
