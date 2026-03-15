<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranslationMatrixResource extends JsonResource
{
    /**
     * Transform the translation matrix into an API response.
     *
     * The resource expects the array returned by TranslationService::getTranslationMatrix():
     * [ content_id => [ locale => status ] ]
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $matrix = is_array($this->resource) ? $this->resource : [];

        $totalContent = count($matrix);

        // Collect unique locales and per-locale completion stats
        $localeSummary = [];

        foreach ($matrix as $localeStatuses) {
            foreach ($localeStatuses as $locale => $status) {
                if (! isset($localeSummary[$locale])) {
                    $localeSummary[$locale] = ['completed' => 0, 'total' => 0];
                }
                $localeSummary[$locale]['total']++;
                if ($status === 'completed') {
                    $localeSummary[$locale]['completed']++;
                }
            }
        }

        $localeCompletion = [];
        foreach ($localeSummary as $locale => $counts) {
            $localeCompletion[$locale] = [
                'completed' => $counts['completed'],
                'total' => $counts['total'],
                'completion_percentage' => $counts['total'] > 0
                    ? round(($counts['completed'] / $counts['total']) * 100, 1)
                    : 0.0,
            ];
        }

        return [
            'matrix' => $matrix,
            'summary' => [
                'total_content' => $totalContent,
                'total_locales' => count($localeCompletion),
                'locales' => $localeCompletion,
            ],
        ];
    }
}
