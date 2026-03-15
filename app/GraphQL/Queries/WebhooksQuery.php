<?php

namespace App\GraphQL\Queries;

use App\Models\Webhook;

final class WebhooksQuery
{
    /**
     * @param  array{spaceId: string}  $args
     * @return \Illuminate\Database\Eloquent\Collection<int, Webhook>
     */
    public function __invoke(mixed $root, array $args): \Illuminate\Database\Eloquent\Collection
    {
        return Webhook::query()
            ->where('space_id', $args['spaceId'])
            ->orderByDesc('created_at')
            ->get();
    }
}
