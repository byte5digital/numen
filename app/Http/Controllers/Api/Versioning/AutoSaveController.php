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
     *
     * Fix 4: body capped at 1 MB (1 048 576 bytes).
     */
    public function save(Content $content, Request $request): JsonResponse
    {
        $this->authorize('modify', $content);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'excerpt' => 'nullable|string|max:2000',
            'body' => 'sometimes|string|max:1048576', // Fix 4: cap body at 1 MB
            'body_format' => 'sometimes|in:markdown,html,blocks',
            'structured_fields' => 'nullable|array',
            'seo_data' => 'nullable|array',
            'blocks_snapshot' => 'nullable|array',
            // Verify the version exists AND belongs to this content item (IDOR guard).
            // Without this, a user could reference a version from a different content/space.
            'base_version_id' => [
                'nullable',
                'exists:content_versions,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($content): void {
                    if ($value !== null) {
                        $belongs = \App\Models\ContentVersion::where('id', $value)
                            ->where('content_id', $content->id)
                            ->exists();
                        if (! $belongs) {
                            $fail('The selected base version does not belong to this content.');
                        }
                    }
                },
            ],
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
        $this->authorize('view', $content);

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
        $this->authorize('modify', $content);

        /** @var User $user */
        $user = $request->user();

        ContentDraft::where('content_id', $content->id)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['message' => 'Auto-save discarded']);
    }
}
