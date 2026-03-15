<?php

namespace App\GraphQL\Mutations;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;

class CreateContent
{
    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * @param  array{input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): Content
    {
        $user = Auth::user();
        $this->authz->authorize($user, 'content.create', $args['input']['space_id'] ?? null);

        $input = $args['input'];
        $termIds = $input['taxonomy_term_ids'] ?? [];
        unset($input['taxonomy_term_ids']);

        $content = Content::create([
            'space_id' => $input['space_id'],
            'content_type_id' => $input['content_type_id'],
            'slug' => $input['slug'],
            'locale' => $input['locale'] ?? 'en',
            'status' => $input['status'] ?? 'draft',
            'hero_image_id' => $input['hero_image_id'] ?? null,
        ]);

        // Create initial version with title/body
        $version = ContentVersion::create([
            'content_id' => $content->id,
            'version_number' => 1,
            'title' => $input['title'],
            'body' => $input['body'] ?? null,
            'body_format' => 'html',
            'status' => 'draft',
            'author_type' => 'user',
            'author_id' => (string) $user->id,
        ]);

        $content->update(['current_version_id' => $version->id]);

        // Attach taxonomy terms if provided
        if (! empty($termIds)) {
            $content->taxonomyTerms()->sync($termIds);
        }

        $this->authz->log($user, 'content.create', $content);

        return $content->load(['currentVersion', 'contentType', 'space']);
    }
}
