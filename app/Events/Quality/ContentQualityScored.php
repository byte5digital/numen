<?php

namespace App\Events\Quality;

use App\Models\ContentQualityScore;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentQualityScored
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ContentQualityScore $score,
    ) {}
}
