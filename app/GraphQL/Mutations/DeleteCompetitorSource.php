<?php

namespace App\GraphQL\Mutations;

use App\Models\CompetitorSource;

class DeleteCompetitorSource
{
    /** @param array{id: string} $args */
    public function __invoke(mixed $root, array $args): ?CompetitorSource
    {
        $source = CompetitorSource::find($args['id']);
        $source?->delete();

        return $source;
    }
}
