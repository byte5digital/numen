<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateVariantsJob;
use App\Models\MediaAsset;
use App\Services\AuthorizationService;
use App\Services\MediaTransformService;
use App\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaEditController extends Controller
{
    public function __construct(
        private readonly MediaTransformService $transformService,
        private readonly MediaUploadService $uploadService,
        private readonly AuthorizationService $authz,
    ) {}

    /**
     * Apply a crop, rotate, or resize operation to a media asset.
     *
     * POST /v1/media/{asset}/edit
     * Body: { operation: 'crop'|'rotate'|'resize', params: {...}, save_as_variant: bool }
     */
    public function edit(Request $request, MediaAsset $asset): JsonResponse
    {
        $this->authz->authorize($request->user(), 'media.update', $asset->space_id);

        $validated = $request->validate([
            'operation' => ['required', 'string', 'in:crop,rotate,resize'],
            'params' => ['required', 'array'],
            'save_as_variant' => ['sometimes', 'boolean'],
        ]);

        $operation = $validated['operation'];
        $params = $validated['params'];

        match ($operation) {
            'crop' => $request->validate([
                'params.x' => ['required', 'integer', 'min:0'],
                'params.y' => ['required', 'integer', 'min:0'],
                'params.width' => ['required', 'integer', 'min:1'],
                'params.height' => ['required', 'integer', 'min:1'],
            ]),
            'rotate' => $request->validate([
                'params.degrees' => ['required', 'integer', 'in:90,180,270,-90,-180,-270'],
            ]),
            'resize' => $request->validate([
                'params.width' => ['required', 'integer', 'min:1'],
                'params.height' => ['required', 'integer', 'min:1'],
                'params.maintain_aspect' => ['sometimes', 'boolean'],
            ]),
        };

        $saveAsVariant = $validated['save_as_variant'] ?? true;

        $updatedAsset = match ($operation) {
            'crop' => $this->transformService->crop(
                $asset,
                (int) $params['x'],
                (int) $params['y'],
                (int) $params['width'],
                (int) $params['height'],
            ),
            'rotate' => $this->transformService->rotate($asset, (int) $params['degrees']),
            'resize' => $this->handleResize($asset, $params, $saveAsVariant),
        };

        GenerateVariantsJob::dispatch($updatedAsset);

        return response()->json($updatedAsset->fresh());
    }

    /**
     * List all variants for a media asset.
     *
     * GET /v1/media/{asset}/variants
     */
    public function variants(Request $request, MediaAsset $asset): JsonResponse
    {
        $this->authz->authorize($request->user(), 'media.read', $asset->space_id);

        $variants = $asset->variants ?? [];
        $metaVariants = $asset->metadata['variants'] ?? [];

        $result = [];

        foreach ($variants as $key => $variant) {
            $result[] = [
                'key' => $key,
                'path' => $variant['path'] ?? null,
                'width' => $variant['width'] ?? null,
                'height' => $variant['height'] ?? null,
                'url' => isset($variant['path'])
                    ? $this->uploadService->getUrl($asset, ['variant' => $key])
                    : null,
            ];
        }

        foreach ($metaVariants as $name => $url) {
            $result[] = [
                'key' => $name,
                'url' => $url,
                'width' => null,
                'height' => null,
                'path' => null,
            ];
        }

        return response()->json([
            'asset_id' => $asset->id,
            'variants' => $result,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function handleResize(MediaAsset $asset, array $params, bool $saveAsVariant): MediaAsset
    {
        $width = (int) $params['width'];
        $height = (int) $params['height'];
        $maintainAspect = (bool) ($params['maintain_aspect'] ?? true);

        $this->transformService->resize($asset, $width, $height, $maintainAspect);

        return $asset->fresh();
    }
}
