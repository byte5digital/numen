<?php

namespace App\GraphQL\Queries;

use App\Models\Vocabulary;

final class VocabulariesQuery
{
    /**
     * @param  array{spaceId: string}  $args
     * @return \Illuminate\Database\Eloquent\Collection<int, Vocabulary>
     */
    public function __invoke(mixed $root, array $args): \Illuminate\Database\Eloquent\Collection
    {
        return Vocabulary::query()
            ->where('space_id', $args['spaceId'])
            ->ordered()
            ->get();
    }
}
