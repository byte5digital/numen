<?php

namespace App\Events\Content;

use App\Models\Content;
use App\Models\ContentVersion;
use Illuminate\Foundation\Events\Dispatchable;

class ContentRolledBack
{
    use Dispatchable;

    public function __construct(
        public readonly Content $content,
        public readonly ContentVersion $newVersion,
        public readonly ContentVersion $targetVersion,
    ) {}
}
