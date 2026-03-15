<?php

namespace App\GraphQL\Mutations;

use App\Models\ContentBrief;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;

class UpdateBrief
{
    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * @param  array{id: string, input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): ContentBrief
    {
        $user = Auth::user();
        $brief = ContentBrief::findOrFail($args['id']);
        $this->authz->authorize($user, 'brief.update', $brief->space_id);

        $input = array_filter($args['input'], fn ($v) => $v !== null);
        $brief->update($input);

        $this->authz->log($user, 'brief.update', $brief);

        return $brief->fresh(['space']);
    }
}
