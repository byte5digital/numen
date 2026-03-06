<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContentResource;
use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContentController extends Controller
{
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
}
