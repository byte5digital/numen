<?php

namespace App\Events\Content;

use App\Models\Content;
use App\Models\ContentDraft;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ContentDraftAutoSaved
{
    use Dispatchable;

    public function __construct(
        public readonly Content $content,
        public readonly User $user,
        public readonly ContentDraft $draft,
    ) {}
}
