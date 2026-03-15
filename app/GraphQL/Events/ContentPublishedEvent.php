<?php

namespace App\GraphQL\Events;

use App\Models\Content;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentPublishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Content $content) {}
}
