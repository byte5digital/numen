<?php

namespace App\Listeners;

use App\Events\Content\ContentPublished;
use App\Jobs\IndexContentForSearchJob;

class IndexContentForSearch
{
    public function handle(ContentPublished $event): void
    {
        IndexContentForSearchJob::dispatch($event->content->id)->onQueue('search');
    }
}
