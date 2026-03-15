<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFolder;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaFolderController extends Controller
{
    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * List all folders for a space, including parent_id, slug, and asset_count.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'space_id' => ['required', 'ulid', 'exists:spaces,id'],
        ]);

        $spaceId = $request->input('space_id');
        $this->authz->authorize($request->user(), 'media.read', $spaceId);

        $folders = MediaFolder::forSpace($spaceId)
            ->withCount('assets')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (MediaFolder $f): array {
                return [
                    'id' => $f->id,
                    'parent_id' => $f->parent_id,
                    'name' => $f->name,
                    'slug' => $f->slug,
                    'sort_order' => $f->sort_order,
                    'asset_count' => $f->assets_count,
                ];
            });

        return response()->json(['data' => $folders]);
    }

    /**
     * Create a new folder inside a space.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'space_id' => ['required', 'ulid', 'exists:spaces,id'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);

        $this->authz->authorize($request->user(), 'media.update', $data['space_id']);

        // If parent_id provided, verify it belongs to the same space
        if (! empty($data['parent_id'])) {
            MediaFolder::where('id', $data['parent_id'])
                ->where('space_id', $data['space_id'])
                ->firstOrFail();
        }

        $slug = \Illuminate\Support\Str::slug($data['name']);

        $folder = MediaFolder::create([
            'space_id' => $data['space_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        return response()->json([
            'data' => [
                'id' => $folder->id,
                'parent_id' => $folder->parent_id,
                'name' => $folder->name,
                'slug' => $folder->slug,
                'asset_count' => 0,
            ],
        ], 201);
    }

    /**
     * Rename an existing folder.
     */
    public function update(Request $request, MediaFolder $folder): JsonResponse
    {
        $this->authz->authorize($request->user(), 'media.update', $folder->space_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $folder->update([
            'name' => $data['name'],
            'slug' => \Illuminate\Support\Str::slug($data['name']),
        ]);

        return response()->json([
            'data' => [
                'id' => $folder->id,
                'parent_id' => $folder->parent_id,
                'name' => $folder->name,
                'slug' => $folder->slug,
                'asset_count' => $folder->asset_count,
            ],
        ]);
    }

    /**
     * Delete a folder — only if it contains no assets and no child folders.
     */
    public function destroy(Request $request, MediaFolder $folder): JsonResponse
    {
        $this->authz->authorize($request->user(), 'media.delete', $folder->space_id);

        if ($folder->assets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a folder that contains assets.',
            ], 422);
        }

        if ($folder->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a folder that contains sub-folders.',
            ], 422);
        }

        $folder->delete();

        return response()->json(['message' => 'Folder deleted.']);
    }

    /**
     * Move a folder to a different parent (or to root when parent_id is null).
     */
    public function move(Request $request, MediaFolder $folder): JsonResponse
    {
        $this->authz->authorize($request->user(), 'media.update', $folder->space_id);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);

        $newParentId = $data['parent_id'] ?? null;

        if ($newParentId !== null) {
            // Verify target parent belongs to same space (prevents cross-space moves)
            MediaFolder::where('id', $newParentId)
                ->where('space_id', $folder->space_id)
                ->firstOrFail();

            // Prevent moving a folder into itself or into one of its own descendants
            $descendantIds = $this->collectDescendantIds($folder);
            if ($newParentId === $folder->id || in_array($newParentId, $descendantIds, true)) {
                return response()->json([
                    'message' => 'Cannot move a folder into itself or one of its descendants.',
                ], 422);
            }
        }

        $folder->update(['parent_id' => $newParentId]);

        return response()->json([
            'data' => [
                'id' => $folder->id,
                'parent_id' => $folder->parent_id,
                'name' => $folder->name,
                'slug' => $folder->slug,
            ],
        ]);
    }

    /**
     * Recursively collect all descendant folder IDs.
     *
     * @return array<int>
     */
    private function collectDescendantIds(MediaFolder $folder): array
    {
        $ids = [];
        foreach ($folder->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->collectDescendantIds($child));
        }

        return $ids;
    }
}
