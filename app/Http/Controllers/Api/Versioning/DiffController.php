<?php

namespace App\Http\Controllers\Api\Versioning;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentVersion;
use App\Services\Versioning\VersioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiffController extends Controller
{
    /**
     * Compare two versions and return a structured diff.
     *
     * Fix 8: authorize before returning content — verifies both ownership and
     * space access via ContentPolicy::view.
     *
     * Query params: ?version_a={id}&version_b={id}
     */
    public function compare(Content $content, Request $request, VersioningService $versioning): JsonResponse
    {
        // Fix 8: space/ownership check before any content is returned
        $this->authorize('view', $content);

        $request->validate([
            'version_a' => 'required|exists:content_versions,id',
            'version_b' => 'required|exists:content_versions,id',
        ]);

        $a = ContentVersion::with('blocks')->findOrFail($request->string('version_a')->toString());
        $b = ContentVersion::with('blocks')->findOrFail($request->string('version_b')->toString());

        // Ensure both versions belong to this content (also enforces space isolation
        // since $content was already authorized above)
        abort_unless(
            $a->content_id === $content->id && $b->content_id === $content->id,
            422,
            'Versions must belong to this content item.',
        );

        $diff = $versioning->diff($a, $b);

        return response()->json(['data' => $diff]);
    }
}
