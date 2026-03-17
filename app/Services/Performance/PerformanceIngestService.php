<?php

namespace App\Services\Performance;

use App\Events\PerformanceEventIngested;
use App\Models\Performance\ContentPerformanceEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PerformanceIngestService
{
    /**
     * Ingest a single performance event with deduplication.
     */
    public function ingestEvent(array $data): ContentPerformanceEvent
    {
        $this->validate($data);

        $occurredAt = isset($data['occurred_at'])
            ? Carbon::parse($data['occurred_at'])
            : Carbon::now();

        $windowStart = $occurredAt->copy()->subMinutes(5);

        $existing = ContentPerformanceEvent::where('session_id', $data['session_id'])
            ->where('content_id', $data['content_id'])
            ->where('event_type', $data['event_type'])
            ->whereBetween('occurred_at', [$windowStart, $occurredAt->copy()->addMinutes(5)])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $event = ContentPerformanceEvent::create([
            'space_id' => $data['space_id'],
            'content_id' => $data['content_id'],
            'event_type' => $data['event_type'],
            'source' => $data['source'],
            'value' => $data['value'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'session_id' => $data['session_id'],
            'visitor_id' => $data['visitor_id'] ?? null,
            'occurred_at' => $occurredAt,
        ]);

        PerformanceEventIngested::dispatch($event);

        return $event;
    }

    /**
     * Ingest a batch of performance events.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return Collection<int, ContentPerformanceEvent>
     */
    public function ingestBatch(array $events): Collection
    {
        $results = new Collection;

        foreach ($events as $eventData) {
            try {
                $results->push($this->ingestEvent($eventData));
            } catch (ValidationException) {
                // Skip invalid events in batch mode — don't fail entire batch
                continue;
            }
        }

        return $results;
    }

    /**
     * @throws ValidationException
     */
    private function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'space_id' => ['required', 'string'],
            'content_id' => ['required', 'string'],
            'event_type' => ['required', 'string', 'in:page_view,click,scroll_depth,time_on_page,bounce,conversion,social_share,view,engagement'],
            'source' => ['required', 'string', 'in:pixel,webhook,api,sdk'],
            'value' => ['nullable', 'numeric'],
            'metadata' => ['nullable', 'array'],
            'session_id' => ['required', 'string'],
            'visitor_id' => ['nullable', 'string'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $validator->validate();
    }
}
