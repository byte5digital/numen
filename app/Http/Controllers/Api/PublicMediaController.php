<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicMediaController extends Controller
{
    public function __construct(
        private readonly MediaUploadService $uploadService,
    ) {}

    /**
     * List public assets (paginated).
     * GET /v1/public/media?space_id=&type=image&tags[]=&folder_id=&page=
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'space_id' => ['required', 'ulid', 'exists:spaces,id'],
            'type' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'folder_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = MediaAsset::where('space_id', $request->input('space_id'))
            ->where('is_public', true);

        if ($request->filled('folder_id')) {
            $query->where('folder_id', $request->input('folder_id'));
        }

        if ($request->filled('type')) {
            $type = $request->input('type');
            if (! str_contains($type, '/')) {
                $query->where('mime_type', 'like', $type.'/%');
            } else {
                $query->where('mime_type', $type);
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

        $items = collect($assets->items())->map(function (MediaAsset $a) {
            return $this->formatAsset($a);
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    /**
     * Get a single public asset.
     * GET /v1/public/media/{asset}
     */
    public function show(string $asset): JsonResponse
    {
        $a = MediaAsset::where('id', $asset)->where('is_public', true)->firstOrFail();

        return response()->json(['data' => $this->formatAsset($a)]);
    }

    /**
     * List items in a public collection.
     * GET /v1/public/media/collections/{collection}
     */
    public function collection(int $collection): JsonResponse
    {
        $col = DB::table('media_collections')->where('id', $collection)->first();
        abort_unless($col !== null, 404);

        $items = MediaAsset::join('media_collection_items', 'media_assets.id', '=', 'media_collection_items.media_asset_id')
            ->where('media_collection_items.collection_id', $collection)
            ->where('media_assets.is_public', true)
            ->orderBy('media_collection_items.sort_order')
            ->select('media_assets.*')
            ->get()
            ->map(fn (MediaAsset $a) => $this->formatAsset($a));

        return response()->json([
            'data' => $col,
            'items' => $items,
            'item_count' => $items->count(),
        ]);
    }

    /**
     * Build a CDN-ready response array for a MediaAsset.
     */
    private function formatAsset(MediaAsset $asset): array
    {
        $base = [
            'id' => $asset->id,
            'filename' => $asset->filename,
            'mime_type' => $asset->mime_type,
            'size_bytes' => $asset->size_bytes,
            'width' => $asset->width ?? null,
            'height' => $asset->height ?? null,
            'duration' => $asset->duration ?? null,
            'alt_text' => $asset->alt_text ?? null,
            'caption' => $asset->caption ?? null,
            'tags' => $asset->tags ?? [],
            'url' => $this->uploadService->getUrl($asset),
            'created_at' => $asset->created_at,
            'updated_at' => $asset->updated_at,
        ];

        // Include variant URLs if present
        if (! empty($asset->variants)) {
            $base['variants'] = collect($asset->variants)->mapWithKeys(function ($variant, $key) use ($asset) {
                $variantAsset = clone $asset;
                $variantAsset->path = $variant['path'] ?? $asset->path;

                return [$key => [
                    'url' => $this->uploadService->getUrl($variantAsset),
                    'width' => $variant['width'] ?? null,
                    'height' => $variant['height'] ?? null,
                    'format' => $variant['format'] ?? null,
                ]];
            })->toArray();
        }

        return $base;
    }
}
