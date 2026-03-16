<?php

namespace App\GraphQL\Mutations;

use App\Models\CompetitorAlert;

class CreateCompetitorAlert
{
    /** @param array{input: array<string, mixed>} $args */
    public function __invoke(mixed $root, array $args): CompetitorAlert
    {
        return CompetitorAlert::create($args['input']);
    }
}
