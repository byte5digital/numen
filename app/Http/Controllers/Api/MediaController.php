<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\Space;
use App\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaUploadService $uploadService,
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

        $query = MediaAsset::where('space_id', $request->input('space_id'));

        if ($request->filled('folder_id')) {
            $query->where('folder_id', $request->input('folder_id'));
        } else {
            // Default to root-level assets when folder_id not specified
            // If ?folder_id=null explicitly passed, handled above; otherwise show all
        }

        if ($request->filled('mime_type')) {
            $mimeType = $request->input('mime_type');
            // Support prefix match, e.g. "image" matches "image/jpeg", "image/png"
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

        /** @var Space $space */
        $space = Space::findOrFail($request->input('space_id'));
        $folder = $request->filled('folder_id')
            ? \App\Models\MediaFolder::findOrFail($request->input('folder_id'))
            : null;

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
     */
    public function show(MediaAsset $asset): JsonResponse
    {
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
        $request->validate([
            'alt_text' => ['nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $asset->update($request->only(['alt_text', 'caption', 'tags', 'folder_id', 'is_public']));

        return response()->json(['data' => $asset->fresh()]);
    }

    /**
     * Delete a media asset and its storage file.
     */
    public function destroy(MediaAsset $asset): JsonResponse
    {
        $this->uploadService->delete($asset);

        return response()->json(null, 204);
    }

    /**
     * Move an asset to a different folder (or root).
     */
    public function move(Request $request, MediaAsset $asset): JsonResponse
    {
        $request->validate([
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);

        $asset->update(['folder_id' => $request->input('folder_id')]);

        return response()->json(['data' => $asset->fresh()]);
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
    /**
     * Get content items that use a specific media asset.
     *
     * TODO(chunk-6): implement real usage tracking via media_asset_usages pivot table.
     */
    public function usage(MediaAsset $asset): JsonResponse
    {
        // Stub — real implementation comes in Chunk 6 (usage tracking system)
        return response()->json([
            'data' => [],
            'meta' => ['total' => 0],
        ]);
    }

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
