<?php

namespace App\GraphQL\Queries;

use App\Models\ContentType;
use Illuminate\Database\Eloquent\Collection;

final class ContentTypesQuery
{
    /**
     * @param  array{spaceId: string}  $args
     * @return Collection<int, ContentType>
     */
    public function __invoke(mixed $root, array $args): Collection
    {
        return ContentType::where('space_id', $args['spaceId'])->get();
    }
}
