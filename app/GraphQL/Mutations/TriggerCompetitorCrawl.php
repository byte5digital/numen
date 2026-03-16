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
        CrawlCompetitorSourceJob::dispatch($source);

        return true;
    }
}
