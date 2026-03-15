<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MediaCollectionController extends Controller
{
    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * List all collections for a space.
     * GET /v1/media/collections?space_id=...
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'space_id' => ['required', 'ulid', 'exists:spaces,id'],
        ]);

        $spaceId = $request->input('space_id');
        $this->authz->authorize($request->user(), 'media.read', $spaceId);

        $collections = DB::table('media_collections')
            ->where('space_id', $spaceId)
            ->orderBy('name')
            ->get()
            ->map(function ($col) {
                $col->items_count = DB::table('media_collection_items')
                    ->where('collection_id', $col->id)
                    ->count();

                return $col;
            });

        return response()->json(['data' => $collections]);
    }

    /**
     * Create a new collection.
     * POST /v1/media/collections
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'space_id' => ['required', 'ulid', 'exists:spaces,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_smart' => ['boolean'],
            'criteria' => ['nullable', 'array'],
        ]);

        $this->authz->authorize($request->user(), 'media.update', $data['space_id']);

        $id = DB::table('media_collections')->insertGetId([
            'space_id' => $data['space_id'],
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_smart' => $data['is_smart'] ?? false,
            'criteria' => isset($data['criteria']) ? json_encode($data['criteria']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $collection = DB::table('media_collections')->find($id);

        return response()->json(['data' => $collection], 201);
    }

    /**
     * Get a single collection with its items.
     * GET /v1/media/collections/{collection}
     */
    public function show(Request $request, int $collection): JsonResponse
    {
        $col = DB::table('media_collections')->where('id', $collection)->first();
        abort_unless($col, 404);

        $this->authz->authorize($request->user(), 'media.read', $col->space_id);

        $items = MediaAsset::join('media_collection_items', 'media_assets.id', '=', 'media_collection_items.media_asset_id')
            ->where('media_collection_items.collection_id', $collection)
            ->orderBy('media_collection_items.sort_order')
            ->select('media_assets.*', 'media_collection_items.sort_order', 'media_collection_items.added_at')
            ->get()
            ->map(fn ($a) => array_merge($a->toArray(), ['url' => $a->url]));

        return response()->json([
            'data' => $col,
            'items' => $items,
        ]);
    }

    /**
     * Rename / update a collection.
     * PATCH /v1/media/collections/{collection}
     */
    public function update(Request $request, int $collection): JsonResponse
    {
        $col = DB::table('media_collections')->where('id', $collection)->first();
        abort_unless($col, 404);

        $this->authz->authorize($request->user(), 'media.update', $col->space_id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'criteria' => ['nullable', 'array'],
        ]);

        $patch = ['updated_at' => now()];
        if (isset($data['name'])) {
            $patch['name'] = $data['name'];
            $patch['slug'] = Str::slug($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $patch['description'] = $data['description'];
        }
        if (array_key_exists('criteria', $data)) {
            $patch['criteria'] = json_encode($data['criteria']);
        }

        DB::table('media_collections')->where('id', $collection)->update($patch);

        return response()->json(['data' => DB::table('media_collections')->find($collection)]);
    }

    /**
     * Delete a collection.
     * DELETE /v1/media/collections/{collection}
     */
    public function destroy(Request $request, int $collection): JsonResponse
    {
        $col = DB::table('media_collections')->where('id', $collection)->first();
        abort_unless($col, 404);

        $this->authz->authorize($request->user(), 'media.delete', $col->space_id);

        DB::table('media_collections')->where('id', $collection)->delete();

        return response()->json(null, 204);
    }

    /**
     * Add an asset to a collection.
     * POST /v1/media/collections/{collection}/items
     */
    public function addItem(Request $request, int $collection): JsonResponse
    {
        $col = DB::table('media_collections')->where('id', $collection)->first();
        abort_unless($col, 404);

        $this->authz->authorize($request->user(), 'media.update', $col->space_id);

        $data = $request->validate([
            'media_asset_id' => ['required', 'string', 'exists:media_assets,id'],
            'sort_order' => ['integer'],
        ]);

        // Verify asset belongs to the same space as the collection
        MediaAsset::where('id', $data['media_asset_id'])
            ->where('space_id', $col->space_id)
            ->firstOrFail();

        DB::table('media_collection_items')->insertOrIgnore([
            'collection_id' => $collection,
            'media_asset_id' => $data['media_asset_id'],
            'sort_order' => $data['sort_order'] ?? 0,
            'added_at' => now(),
        ]);

        return response()->json(['message' => 'Asset added to collection.'], 201);
    }

    /**
     * Remove an asset from a collection.
     * DELETE /v1/media/collections/{collection}/items/{asset}
     */
    public function removeItem(Request $request, int $collection, string $asset): JsonResponse
    {
        $col = DB::table('media_collections')->where('id', $collection)->first();
        abort_unless($col, 404);

        $this->authz->authorize($request->user(), 'media.update', $col->space_id);

        DB::table('media_collection_items')
            ->where('collection_id', $collection)
            ->where('media_asset_id', $asset)
            ->delete();

        return response()->json(null, 204);
    }
}
