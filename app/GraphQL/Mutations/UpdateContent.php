<?php

namespace App\GraphQL\Mutations;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;

class UpdateContent
{
    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * @param  array{id: string, input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): Content
    {
        $user = Auth::user();
        $content = Content::findOrFail($args['id']);
        $this->authz->authorize($user, 'content.update', $content->space_id);

        $input = $args['input'];
        $termIds = $input['taxonomy_term_ids'] ?? null;
        unset($input['taxonomy_term_ids']);

        // Update top-level content fields (slug, status, hero_image_id)
        $contentFields = [];
        if (isset($input['slug'])) {
            $contentFields['slug'] = $input['slug'];
        }
        if (isset($input['status'])) {
            $contentFields['status'] = $input['status'];
        }
        if (array_key_exists('hero_image_id', $input)) {
            $contentFields['hero_image_id'] = $input['hero_image_id'];
        }

        if (! empty($contentFields)) {
            $content->update($contentFields);
        }

        // Create a new version when title or body changes
        if (isset($input['title']) || isset($input['body'])) {
            $prev = $content->currentVersion;
            $prevNumber = $prev !== null ? $prev->version_number : 0;
            $prevTitle = $prev !== null ? $prev->title : '';
            $prevBody = $prev !== null ? $prev->body : null;
            $prevFormat = $prev !== null ? $prev->body_format : 'html';
            $prevExcerpt = $prev !== null ? $prev->excerpt : null;
            $prevSeo = $prev !== null ? $prev->seo_data : null;
            $prevFields = $prev !== null ? $prev->structured_fields : null;

            $version = ContentVersion::create([
                'content_id' => $content->id,
                'version_number' => $prevNumber + 1,
                'title' => $input['title'] ?? $prevTitle,
                'body' => $input['body'] ?? $prevBody,
                'body_format' => $prevFormat,
                'status' => 'draft',
                'author_type' => 'user',
                'author_id' => (string) $user->id,
                'parent_version_id' => $prev?->id,
                'excerpt' => $prevExcerpt,
                'seo_data' => $prevSeo,
                'structured_fields' => $prevFields,
            ]);

            $content->update(['current_version_id' => $version->id]);
        }

        // Sync taxonomy terms if provided
        if ($termIds !== null) {
            $content->taxonomyTerms()->sync($termIds);
        }

        $this->authz->log($user, 'content.update', $content);

        return $content->fresh(['currentVersion', 'contentType', 'space']);
    }
}
