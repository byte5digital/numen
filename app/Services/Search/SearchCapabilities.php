<?php

namespace App\Services\Search;

/**
 * Runtime capabilities snapshot — which tiers are available.
 */
final class SearchCapabilities
{
    public function __construct(
        private readonly bool $instant,
        private readonly bool $semantic,
        private readonly bool $ask,
    ) {}

    public function hasInstant(): bool
    {
        return $this->instant && (bool) config('numen.search.tiers_enabled.instant', true);
    }

    public function hasSemantic(): bool
    {
        return $this->semantic && (bool) config('numen.search.tiers_enabled.semantic', true);
    }

    public function hasAsk(): bool
    {
        return $this->ask && (bool) config('numen.search.tiers_enabled.ask', true);
    }

    /** @return array<string, bool> */
    public function toArray(): array
    {
        return [
            'instant' => $this->hasInstant(),
            'semantic' => $this->hasSemantic(),
            'ask' => $this->hasAsk(),
        ];
    }
}
