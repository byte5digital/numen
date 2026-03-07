<?php

namespace App\Http\Controllers\Api\Versioning;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentDraft;
use App\Models\User;
use App\Services\Versioning\VersioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutoSaveController extends Controller
{
    public function __construct(private VersioningService $versioning) {}

    /**
     * Auto-save (upsert) draft content for the authenticated user.
     */
    public function save(Content $content, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'excerpt' => 'nullable|string|max:2000',
            'body' => 'sometimes|string',
            'body_format' => 'sometimes|in:markdown,html,blocks',
            'structured_fields' => 'nullable|array',
            'seo_data' => 'nullable|array',
            'blocks_snapshot' => 'nullable|array',
            'base_version_id' => 'nullable|exists:content_versions,id',
        ]);

        /** @var User $user */
        $user = $request->user();

        $draft = $this->versioning->autoSave($content, $user, $validated);

        return response()->json(['data' => $draft]);
    }

    /**
     * Get the current auto-save draft for the authenticated user.
     */
    public function show(Content $content, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $draft = ContentDraft::where('content_id', $content->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $draft) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $draft]);
    }

    /**
     * Discard the auto-save draft for the authenticated user.
     */
    public function discard(Content $content, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        ContentDraft::where('content_id', $content->id)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['message' => 'Auto-save discarded']);
    }
}
