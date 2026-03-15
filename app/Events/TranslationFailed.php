<?php

namespace App\Events;

use App\Models\ContentTranslationJob;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ContentTranslationJob $job,
        public readonly string $reason,
    ) {}
}
