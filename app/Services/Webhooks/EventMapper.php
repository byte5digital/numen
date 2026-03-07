<?php

namespace App\Services\Webhooks;

use Illuminate\Support\Str;

class EventMapper
{
    /**
     * Map a domain event to a webhook payload array.
     *
     * @param  string  $eventType  e.g. "content.published"
     * @param  array  $context  Model data / relevant fields
     * @return array Serialised payload for delivery
     */
    public function map(string $eventType, array $context): array
    {
        [$domain] = explode('.', $eventType, 2);

        $data = match ($domain) {
            'content' => $this->mapContentEvent($eventType, $context),
            'pipeline' => $this->mapPipelineEvent($eventType, $context),
            'media' => $this->mapMediaEvent($eventType, $context),
            'user' => $this->mapUserEvent($eventType, $context),
            default => $context,
        };

        return [
            'id' => (string) Str::ulid(),
            'event' => $eventType,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];
    }

    private function mapContentEvent(string $eventType, array $context): array
    {
        return array_filter([
            'content_id' => $context['id'] ?? $context['content_id'] ?? null,
            'space_id' => $context['space_id'] ?? null,
            'title' => $context['title'] ?? null,
            'content_type' => $context['content_type'] ?? $context['type'] ?? null,
            'published_at' => $context['published_at'] ?? null,
            'version' => $context['version'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function mapPipelineEvent(string $eventType, array $context): array
    {
        return array_filter([
            'pipeline_id' => $context['pipeline_id'] ?? $context['id'] ?? null,
            'space_id' => $context['space_id'] ?? null,
            'run_id' => $context['run_id'] ?? null,
            'stage' => $context['stage'] ?? null,
            'status' => $context['status'] ?? null,
            'ai_score' => $context['ai_score'] ?? null,
            'completed_at' => $context['completed_at'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function mapMediaEvent(string $eventType, array $context): array
    {
        return array_filter([
            'asset_id' => $context['id'] ?? $context['asset_id'] ?? null,
            'space_id' => $context['space_id'] ?? null,
            'filename' => $context['filename'] ?? $context['original_filename'] ?? null,
            'mime_type' => $context['mime_type'] ?? null,
            'url' => $context['url'] ?? $context['public_url'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function mapUserEvent(string $eventType, array $context): array
    {
        return array_filter([
            'user_id' => $context['id'] ?? $context['user_id'] ?? null,
            'space_id' => $context['space_id'] ?? null,
            'action' => $context['action'] ?? $eventType,
        ], fn ($v) => $v !== null);
    }
}
