<?php

namespace App\Jobs;

use App\Models\CompetitorContentItem;
use App\Models\Content;
use App\Services\Competitor\ContentFingerprintService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FingerprintContentJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly Model $fingerprintable,
    ) {
        $this->onQueue('competitor');
    }

    public function handle(ContentFingerprintService $service): void
    {
        $type = $this->fingerprintable::class;
        $id = $this->fingerprintable->getKey();

        if (
            ! $this->fingerprintable instanceof Content
            && ! $this->fingerprintable instanceof CompetitorContentItem
        ) {
            Log::warning('FingerprintContentJob: unsupported model type', ['type' => $type]);

            return;
        }

        Log::info('FingerprintContentJob: fingerprinting', ['type' => $type, 'id' => $id]);

        $fingerprint = $service->fingerprint($this->fingerprintable);

        Log::info('FingerprintContentJob: done', [
            'type' => $type,
            'id' => $id,
            'fingerprint_id' => $fingerprint->id,
            'topics' => count($fingerprint->topics ?? []),
            'entities' => count($fingerprint->entities ?? []),
            'keywords' => count($fingerprint->keywords ?? []),
        ]);
    }
}
