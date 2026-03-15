<?php

namespace App\GraphQL\Mutations;

use App\Models\Content;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;

class PublishContent
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

        // Use the model's publish() method to ensure consistent status transitions
        $content->publish();

        $this->authz->log($user, 'content.publish', $content);

        return $content->fresh(['currentVersion', 'contentType', 'space']);
    }
}
