<?php

namespace App\GraphQL\Mutations;

use App\Models\CompetitorAlert;

class DeleteCompetitorAlert
{
    /** @param array{id: string} $args */
    public function __invoke(mixed $root, array $args): ?CompetitorAlert
    {
        $alert = CompetitorAlert::find($args['id']);
        $alert?->delete();

        return $alert;
    }
}
