<?php

namespace App\Services\Competitor\Crawlers;

use App\Models\CompetitorContentItem;
use App\Models\CompetitorSource;
use Illuminate\Support\Collection;

interface CrawlerContract
{
    /**
     * Crawl a competitor source and return discovered content items.
     *
     * @return Collection<int, CompetitorContentItem>
     */
    public function crawl(CompetitorSource $source): Collection;

    /**
     * Returns true if this crawler handles the given type string.
     */
    public function supports(string $type): bool;
}
