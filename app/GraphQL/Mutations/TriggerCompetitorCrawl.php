<?php

namespace App\GraphQL\Mutations;

use App\Jobs\CrawlCompetitorSourceJob;
use App\Models\CompetitorSource;

class TriggerCompetitorCrawl
{
    /** @param array{source_id: string} $args */
    public function __invoke(mixed $root, array $args): bool
    {
        $source = CompetitorSource::findOrFail($args['source_id']);

        $currentSpace = app()->bound('current_space') ? app('current_space') : null;
        abort_if($currentSpace && $source->space_id !== $currentSpace->id, 403);

        CrawlCompetitorSourceJob::dispatch($source);

        return true;
    }
}
