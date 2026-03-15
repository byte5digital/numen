<?php

namespace App\Services\Chat;

use App\Models\Space;

class SuggestionService
{
    private const DEFAULT_SUGGESTIONS = [
        'Create content',
        'Show drafts',
        'Run pipeline',
        'Help',
    ];

    private const ROUTE_SUGGESTIONS = [
        'dashboard' => [
            'Show recent drafts',
            'Create a blog post',
            "What's pending review?",
        ],
        'content' => [
            'Filter by published',
            'Create new content',
            'Show content stats',
        ],
        'pipeline' => [
            'Run pipeline',
            'Show failed runs',
            "What's queued?",
        ],
    ];

    /**
     * Get context-appropriate suggestions based on the current route and space.
     *
     * @return array<int, string>
     */
    public function getSuggestions(string $currentRoute, Space $space): array
    {
        foreach (self::ROUTE_SUGGESTIONS as $key => $suggestions) {
            if (str_contains(strtolower($currentRoute), $key)) {
                return $suggestions;
            }
        }

        return self::DEFAULT_SUGGESTIONS;
    }
}
