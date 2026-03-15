<?php

namespace App\GraphQL\Queries;

use App\Models\ContentPipeline;

final class PipelinesQuery
{
    /**
     * @param  array{spaceId: string}  $args
     * @return \Illuminate\Database\Eloquent\Collection<int, ContentPipeline>
     */
    public function __invoke(mixed $root, array $args): \Illuminate\Database\Eloquent\Collection
    {
        return ContentPipeline::query()
            ->where('space_id', $args['spaceId'])
            ->orderBy('name')
            ->get();
    }
}
