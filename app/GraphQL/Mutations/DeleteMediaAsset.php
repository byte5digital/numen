<?php

namespace App\GraphQL\Mutations;

use App\Models\MediaAsset;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;

class DeleteMediaAsset
{
    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): MediaAsset
    {
        $user = Auth::user();
        $asset = MediaAsset::findOrFail($args['id']);
        $this->authz->authorize($user, 'media.delete', $asset->space_id);

        $this->authz->log($user, 'media.delete', $asset);

        $asset->delete();

        return $asset;
    }
}
