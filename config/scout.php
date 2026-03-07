<?php

use App\Models\Content;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    | Supported: "meilisearch", "algolia", "database", "null"
    | Falls back to "database" when SCOUT_DRIVER is not set.
    */
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),

    'prefix' => env('SCOUT_PREFIX', ''),

    'queue' => env('SCOUT_QUEUE', true),

    'after_commit' => false,

    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    'soft_delete' => false,

    'identify' => env('SCOUT_IDENTIFY', false),

    /*
    |--------------------------------------------------------------------------
    | Meilisearch Configuration
    |--------------------------------------------------------------------------
    */
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'timeout' => 5,
        'index-settings' => [
            Content::class => [
                'filterableAttributes' => [
                    'content_type',
                    'space_id',
                    'locale',
                    'status',
                    'published_at',
                ],
                'sortableAttributes' => ['published_at', 'updated_at'],
                'searchableAttributes' => [
                    'title',
                    'excerpt',
                    'seo_title',
                    'seo_description',
                    'body',
                    'blocks_text',
                ],
                'typoTolerance' => [
                    'enabled' => true,
                    'minWordSizeForTypos' => [
                        'oneTypo' => 4,
                        'twoTypos' => 8,
                    ],
                ],
                'pagination' => [
                    'maxTotalHits' => 1000,
                ],
            ],
        ],
    ],

];
