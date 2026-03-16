<?php

namespace App\GraphQL\Mutations;

use App\Models\CompetitorSource;

class DeleteCompetitorSource
{
    /** @param array{id: string} $args */
    public function __invoke(mixed $root, array $args): ?CompetitorSource
    {
        $source = CompetitorSource::find($args['id']);

        if ($source) {
            $currentSpace = app()->bound('current_space') ? app('current_space') : null;
            abort_if($currentSpace && $source->space_id !== $currentSpace->id, 403);

            $source->delete();
        }

        return $source;
    }
}
