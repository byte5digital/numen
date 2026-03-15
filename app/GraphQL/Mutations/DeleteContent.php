<?php

namespace App\GraphQL\Mutations;

use App\Models\Content;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;

class DeleteContent
{
    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): Content
    {
        $user = Auth::user();
        $content = Content::findOrFail($args['id']);
        $this->authz->authorize($user, 'content.delete', $content->space_id);

        // Load relations before soft-delete for return value
        $content->load(['currentVersion', 'contentType', 'space']);

        $this->authz->log($user, 'content.delete', $content);

        // Soft delete (Content uses SoftDeletes trait)
        $content->delete();

        return $content;
    }
}
