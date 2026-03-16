<?php

namespace App\GraphQL\Mutations;

use App\Models\CompetitorSource;

class UpdateCompetitorSource
{
    /** @param array{id: string, input: array<string, mixed>} $args */
    public function __invoke(mixed $root, array $args): CompetitorSource
    {
        $source = CompetitorSource::findOrFail($args['id']);
        $source->update($args['input']);

        return $source->fresh() ?? $source;
    }
}
