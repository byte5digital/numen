<?php

namespace App\GraphQL\Mutations;

use App\Models\MediaAsset;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;

class UpdateMediaAsset
{
    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * @param  array{id: string, input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): MediaAsset
    {
        $user = Auth::user();
        $asset = MediaAsset::findOrFail($args['id']);
        $this->authz->authorize($user, 'media.update', $asset->space_id);

        $input = array_filter($args['input'], fn ($v) => $v !== null);
        $asset->update($input);

        $this->authz->log($user, 'media.update', $asset);

        return $asset->fresh();
    }
}
