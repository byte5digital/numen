<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\Space;
use App\Services\AuthorizationService;
use App\Services\MediaUploadService;
use App\Services\MediaUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaUploadService $uploadService,
        private readonly MediaUsageService $usageService,
        private readonly AuthorizationService $authz,
    ) {}

    /**
     * List media assets for a space, with optional filters.
     * Query params: folder_id, mime_type, tags[], per_page, page, search
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'space_id' => ['required', 'ulid', 'exists:spaces,id'],
            'folder_id' => ['nullable', 'integer'],
            'mime_type' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $spaceId = $request->input('space_id');
        $this->authz->authorize($request->user(), 'media.read', $spaceId);

        $query = MediaAsset::where('space_id', $spaceId);

        if ($request->filled('folder_id')) {
            $query->where('folder_id', $request->input('folder_id'));
        }

        if ($request->filled('mime_type')) {
            $mimeType = $request->input('mime_type');
            if (! str_contains($mimeType, '/')) {
                $query->where('mime_type', 'like', $mimeType.'/%');
            } else {
                $query->where('mime_type', $mimeType);
            }
        }

        if ($request->filled('tags')) {
            foreach ($request->input('tags') as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('filename', 'like', $search)
                    ->orWhere('alt_text', 'like', $search)
                    ->orWhere('caption', 'like', $search);
            });
        }

        $perPage = (int) $request->input('per_page', 30);
        $assets = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $assets->items(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    /**
     * Upload a new media asset.
     */
    public function store(Request $request): JsonResponse
    {
        $supportedMimes = $this->uploadService->getSupportedMimeTypes();
        $maxKb = (int) ($this->uploadService->getMaxFileSizeBytes() / 1024);

        $request->validate([
            'space_id' => ['required', 'ulid', 'exists:spaces,id'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'file' => ['required', 'file', 'mimes:'.implode(',', $this->mimeTypesToExtensions($supportedMimes)), 'max:'.$maxKb],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
        ]);

        $spaceId = $request->input('space_id');
        $this->authz->authorize($request->user(), 'media.upload', $spaceId);

        /** @var Space $space */
        $space = Space::findOrFail($spaceId);

        // If folder_id provided, verify it belongs to this space (prevents cross-space folder injection)
        $folder = null;
        if ($request->filled('folder_id')) {
            $folder = \App\Models\MediaFolder::where('id', $request->input('folder_id'))
                ->where('space_id', $spaceId)
                ->firstOrFail();
        }

        $asset = $this->uploadService->upload($request->file('file'), $space, $folder);

        // Apply optional metadata provided at upload time
        $meta = array_filter([
            'alt_text' => $request->input('alt_text'),
            'caption' => $request->input('caption'),
            'tags' => $request->input('tags'),
        ], fn ($v) => $v !== null);

        if (! empty($meta)) {
            $asset->update($meta);
        }

        return response()->json(['data' => $asset->fresh()], 201);
    }

    /**
     * Get a single asset with its URL resolved.
     * Verifies asset belongs to a space the user can access.
     */
    public function show(Request $request, MediaAsset $asset): JsonResponse
    {
        $this->authz->authorize($request->user(), 'media.read', $asset->space_id);

        return response()->json([
            'data' => array_merge($asset->toArray(), [
                'url' => $this->uploadService->getUrl($asset),
            ]),
        ]);
    }

    /**
     * Update asset metadata (alt, caption, tags, folder_id).
     */
    public function update(Request $request, MediaAsset $asset): JsonResponse
    {
        $this->authz->authorize($request->user(), 'media.update', $asset->space_id);

        $request->validate([
            'alt_text' => ['nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'folder_id' => ['nullable', 'integer'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        // If folder_id is being changed, verify the target folder belongs to same space
        if ($request->has('folder_id') && $request->input('folder_id') !== null) {
            \App\Models\MediaFolder::where('id', $request->input('folder_id'))
                ->where('space_id', $asset->space_id)
                ->firstOrFail();
        }

        $asset->update($request->only(['alt_text', 'caption', 'tags', 'folder_id', 'is_public']));

        return response()->json(['data' => $asset->fresh()]);
    }

    /**
     * Delete a media asset and its storage file.
     */
    public function destroy(Request $request, MediaAsset $asset): JsonResponse
    {
        $this->authz->authorize($request->user(), 'media.delete', $asset->space_id);

        $this->uploadService->delete($asset);

        return response()->json(null, 204);
    }

    /**
     * Move an asset to a different folder (or root).
     */
    public function move(Request $request, MediaAsset $asset): JsonResponse
    {
        $this->authz->authorize($request->user(), 'media.update', $asset->space_id);

        $request->validate([
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);

        // Verify target folder belongs to same space
        if ($request->filled('folder_id')) {
            \App\Models\MediaFolder::where('id', $request->input('folder_id'))
                ->where('space_id', $asset->space_id)
                ->firstOrFail();
        }

        $asset->update(['folder_id' => $request->input('folder_id')]);

        return response()->json(['data' => $asset->fresh()]);
    }

    /**
     * Get content items that use a specific media asset.
     */
    public function usage(Request $request, MediaAsset $asset): JsonResponse
    {
        $this->authz->authorize($request->user(), 'media.read', $asset->space_id);

        $usages = $this->usageService->getUsagesForAsset($asset);

        return response()->json([
            'data' => $usages->values(),
            'meta' => ['total' => $usages->count()],
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Convert MIME types to file extensions for Laravel's `mimes` validation rule.
     *
     * @param  string[]  $mimes
     * @return string[]
     */
    private function mimeTypesToExtensions(array $mimes): array
    {
        $map = [
            'image/jpeg' => 'jpeg,jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/avif' => 'avif',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogv',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'oga',
            'audio/wav' => 'wav',
            'audio/webm' => 'weba',
        ];

        $extensions = [];
        foreach ($mimes as $mime) {
            if (isset($map[$mime])) {
                foreach (explode(',', $map[$mime]) as $ext) {
                    $extensions[] = trim($ext);
                }
            }
        }

        return array_unique($extensions ?: ['jpeg', 'jpg', 'png', 'gif', 'webp', 'pdf']);
    }
}
