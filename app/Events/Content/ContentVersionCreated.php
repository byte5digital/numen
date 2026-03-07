<?php

namespace App\Events\Content;

use App\Models\Content;
use App\Models\ContentVersion;
use Illuminate\Foundation\Events\Dispatchable;

class ContentVersionCreated
{
    use Dispatchable;

    /**
     * @param  string  $source  'human' | 'pipeline' | 'rollback'
     */
    public function __construct(
        public readonly Content $content,
        public readonly ContentVersion $version,
        public readonly string $source = 'human',
    ) {}
}
