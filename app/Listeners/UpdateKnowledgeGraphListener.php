<?php

namespace App\Listeners;

use App\Events\Content\ContentPublished;
use App\Jobs\UpdateKnowledgeGraphJob;

class UpdateKnowledgeGraphListener
{
    public function handle(ContentPublished $event): void
    {
        if (! config('numen.graph.enabled', false)) {
            return;
        }

        UpdateKnowledgeGraphJob::dispatch($event->content)->onQueue('graph');
    }
}
