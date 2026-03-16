<?php

namespace App\GraphQL\Mutations;

use App\Models\CompetitorAlert;

class DeleteCompetitorAlert
{
    /** @param array{id: string} $args */
    public function __invoke(mixed $root, array $args): ?CompetitorAlert
    {
        $alert = CompetitorAlert::find($args['id']);

        if ($alert) {
            $currentSpace = app()->bound('current_space') ? app('current_space') : null;
            abort_if($currentSpace && $alert->space_id !== $currentSpace->id, 403);

            $alert->delete();
        }

        return $alert;
    }
}
