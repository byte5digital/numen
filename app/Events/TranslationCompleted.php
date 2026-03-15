<?php

namespace App\Events;

use App\Models\Content;
use App\Models\ContentTranslationJob;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ContentTranslationJob $job,
        public readonly Content $translatedContent,
    ) {}
}
