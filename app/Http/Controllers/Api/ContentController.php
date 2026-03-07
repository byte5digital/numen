<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContentResource;
use App\Models\Content;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContentController extends Controller
{
    public function __construct(private AuthorizationService $authz) {}

    /**
     * List published content with filtering.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Content::query()
            ->published()
            ->with(['currentVersion', 'contentType', 'mediaAssets']);

        // Filters
        if ($locale = $request->query('locale')) {
            $query->forLocale($locale);
        }

        if ($type = $request->query('type')) {
            $query->ofType($type);
        }

        if ($tag = $request->query('tag')) {
            $tags = explode(',', $tag);
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $t) {
                    $q->whereJsonContains('taxonomy->tags', trim($t));
                }
            });
        }

        // Sorting — whitelist valid columns to prevent column enumeration
        $allowedSorts = ['published_at', 'created_at', 'updated_at', 'title'];
        $sort = $request->query('sort', '-published_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $column = in_array($column, $allowedSorts) ? $column : 'published_at';
        $query->orderBy($column, $direction);

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return ContentResource::collection($query->paginate($perPage));
    }

    /**
     * Get single content by slug.
     */
    public function show(Request $request, string $slug): ContentResource
    {
        $locale = $request->query('locale', 'en');

        $content = Content::query()
            ->published()
            ->where('slug', $slug)
            ->forLocale($locale)
            ->with(['currentVersion', 'contentType', 'mediaAssets', 'versions'])
            ->firstOrFail();

        return new ContentResource($content);
    }

    /**
     * List content by type.
     */
    public function byType(Request $request, string $type): AnonymousResourceCollection
    {
        $query = Content::query()
            ->published()
            ->ofType($type)
            ->with(['currentVersion', 'contentType'])
            ->orderByDesc('published_at');

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return ContentResource::collection($query->paginate($perPage));
    }

    /**
     * Create new content. Requires content.create permission.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authz->authorize($request->user(), 'content.create');

        $data = $request->validate([
            'slug'             => ['required', 'string', 'unique:contents,slug'],
            'content_type_id'  => ['required', 'string', 'exists:content_types,id'],
            'space_id'         => ['required', 'string', 'exists:spaces,id'],
            'locale'           => ['sometimes', 'string', 'max:10'],
            'status'           => ['sometimes', 'string', 'in:draft,published,archived'],
        ]);

        $content = Content::create(array_merge(['status' => 'draft', 'locale' => 'en'], $data));

        $this->authz->log($request->user(), 'content.create', $content);

        return response()->json(['data' => $content], 201);
    }

    /**
     * Update existing content. Requires content.update permission.
     */
    public function update(Request $request, string $id): ContentResource
    {
        $this->authz->authorize($request->user(), 'content.update');

        $content = Content::findOrFail($id);

        $data = $request->validate([
            'slug'   => ['sometimes', 'string', 'unique:contents,slug,' . $id],
            'status' => ['sometimes', 'string', 'in:draft,published,archived'],
            'locale' => ['sometimes', 'string', 'max:10'],
        ]);

        $content->update($data);

        $this->authz->log($request->user(), 'content.update', $content);

        return new ContentResource($content->fresh(['currentVersion', 'contentType']));
    }

    /**
     * Delete content. Requires content.delete permission.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authz->authorize($request->user(), 'content.delete');

        $content = Content::findOrFail($id);
        $this->authz->log($request->user(), 'content.delete', $content);
        $content->delete();

        return response()->json(['message' => 'Content deleted.']);
    }
}
