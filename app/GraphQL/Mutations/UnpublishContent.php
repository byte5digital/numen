<?php

namespace App\GraphQL\Mutations;

use App\Models\Content;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;

class UnpublishContent
{
    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): Content
    {
        $user = Auth::user();
        $content = Content::findOrFail($args['id']);
        $this->authz->authorize($user, 'content.publish', $content->space_id);

        $content->update(['status' => 'draft']);

        $this->authz->log($user, 'content.unpublish', $content);

        return $content->fresh(['currentVersion', 'contentType', 'space']);
    }
}
