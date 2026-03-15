<?php

namespace App\GraphQL\Queries;

use App\Models\Persona;

final class PersonasQuery
{
    /**
     * @param  array{spaceId: string}  $args
     * @return \Illuminate\Database\Eloquent\Collection<int, Persona>
     */
    public function __invoke(mixed $root, array $args): \Illuminate\Database\Eloquent\Collection
    {
        return Persona::query()
            ->where('space_id', $args['spaceId'])
            ->orderBy('name')
            ->get();
    }
}
