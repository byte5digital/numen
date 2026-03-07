<?php

namespace App\Events\Content;

use App\Models\Content;
use App\Models\ContentVersion;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;

class ContentScheduled
{
    use Dispatchable;

    public function __construct(
        public readonly Content $content,
        public readonly ContentVersion $version,
        public readonly Carbon $publishAt,
    ) {}
}
