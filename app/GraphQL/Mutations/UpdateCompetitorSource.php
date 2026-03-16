<?php

namespace App\GraphQL\Mutations;

use App\Models\CompetitorSource;

class UpdateCompetitorSource
{
    /** @param array{id: string, input: array<string, mixed>} $args */
    public function __invoke(mixed $root, array $args): CompetitorSource
    {
        $source = CompetitorSource::findOrFail($args['id']);

        $currentSpace = app()->bound('current_space') ? app('current_space') : null;
        abort_if($currentSpace && $source->space_id !== $currentSpace->id, 403);

        $source->update($args['input']);

        return $source->fresh() ?? $source;
    }
}
