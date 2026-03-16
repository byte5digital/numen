<?php

namespace App\GraphQL\Mutations;

use App\Models\CompetitorSource;

class CreateCompetitorSource
{
    /** @param array{input: array<string, mixed>} $args */
    public function __invoke(mixed $root, array $args): CompetitorSource
    {
        return CompetitorSource::create($args['input']);
    }
}
