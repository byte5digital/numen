<?php

namespace App\Services\Versioning;

use App\Models\ContentVersion;
use JsonSerializable;

class VersionDiff implements JsonSerializable
{
    public function __construct(
        public readonly ContentVersion $versionA,
        public readonly ContentVersion $versionB,
        /** @var array<string, array<string, mixed>> */
        public readonly array $fieldDiffs,
        /** @var array<int, array<string, mixed>> */
        public readonly array $blockDiffs,
        /** @var array<string, array<string, mixed>> */
        public readonly array $seoDiffs,
    ) {}

    public function hasChanges(): bool
    {
        return ! empty($this->fieldDiffs)
            || ! empty($this->blockDiffs)
            || ! empty($this->seoDiffs);
    }

    public function summary(): string
    {
        $parts = [];

        if (isset($this->fieldDiffs['title'])) {
            $parts[] = 'title changed';
        }

        if (isset($this->fieldDiffs['body'])) {
            $stats = $this->fieldDiffs['body']['stats'] ?? [];
            $parts[] = sprintf(
                'body: +%d/-%d lines',
                $stats['lines_added'] ?? 0,
                $stats['lines_removed'] ?? 0,
            );
        }

        $added = count(array_filter($this->blockDiffs, fn ($d) => $d['type'] === 'added'));
        $removed = count(array_filter($this->blockDiffs, fn ($d) => $d['type'] === 'removed'));

        if ($added) {
            $parts[] = "$added blocks added";
        }

        if ($removed) {
            $parts[] = "$removed blocks removed";
        }

        if (! empty($this->seoDiffs)) {
            $parts[] = count($this->seoDiffs).' SEO fields changed';
        }

        return implode(', ', $parts) ?: 'No changes';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'version_a' => $this->versionA->only(['id', 'version_number', 'label', 'created_at']),
            'version_b' => $this->versionB->only(['id', 'version_number', 'label', 'created_at']),
            'has_changes' => $this->hasChanges(),
            'summary' => $this->summary(),
            'fields' => $this->fieldDiffs,
            'blocks' => $this->blockDiffs,
            'seo' => $this->seoDiffs,
        ];
    }
}
