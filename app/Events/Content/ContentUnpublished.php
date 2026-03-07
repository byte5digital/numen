<?php

namespace App\Events\Content;

use App\Models\Content;
use Illuminate\Foundation\Events\Dispatchable;

class ContentUnpublished
{
    use Dispatchable;

    public function __construct(public Content $content) {}
}
