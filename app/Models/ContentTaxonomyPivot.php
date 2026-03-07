<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;

class ContentTaxonomyPivot extends Pivot
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'content_taxonomy';

    protected static function booted(): void
    {
        static::creating(function (self $pivot): void {
            if (empty($pivot->id)) {
                $pivot->id = (string) Str::ulid();
            }
        });
    }
}
