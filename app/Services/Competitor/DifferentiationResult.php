<?php

namespace App\Services\Competitor;

readonly class DifferentiationResult
{
    /**
     * @param  array<string>  $angles  Unique angles our content could take
     * @param  array<string>  $gaps  Topics/perspectives competitors missed
     * @param  array<string>  $recommendations  Specific action items for differentiation
     */
    public function __construct(
        public float $similarityScore,
        public float $differentiationScore,
        public array $angles,
        public array $gaps,
        public array $recommendations,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'similarity_score' => $this->similarityScore,
            'differentiation_score' => $this->differentiationScore,
            'angles' => $this->angles,
            'gaps' => $this->gaps,
            'recommendations' => $this->recommendations,
        ];
    }
}
