<?php

namespace App\Listeners;

use App\Events\Content\ContentPublished;
use App\Jobs\FingerprintContentJob;
use Illuminate\Support\Facades\Log;

class FingerprintPublishedContent
{
    public function handle(ContentPublished $event): void
    {
        $content = $event->content;

        Log::info('FingerprintPublishedContent: dispatching fingerprint job', [
            'content_id' => $content->id,
        ]);

        FingerprintContentJob::dispatch($content);
    }
}
