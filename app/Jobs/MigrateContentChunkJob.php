<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Models\Migration\MigrationCheckpoint;
use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Imports a chunk of content items from migration_items into Numen Content records.
 *
 * Works on items with status "transformed" for the given session, applying offset/limit.
 */
class MigrateContentChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $sessionId,
        public readonly int $offset,
        public readonly int $limit,
    ) {
        $this->onQueue('migration');
    }

    public function handle(): void
    {
        $session = MigrationSession::find($this->sessionId);

        if (! $session) {
            Log::warning('MigrateContentChunkJob: session not found', ['session_id' => $this->sessionId]);

            return;
        }

        if ($session->status === 'paused' || $session->status === 'cancelled') {
            Log::info('MigrateContentChunkJob: session paused/cancelled, skipping', ['session_id' => $this->sessionId]);

            return;
        }

        $items = MigrationItem::query()
            ->where('migration_session_id', $this->sessionId)
            ->where('status', 'transformed')
            ->orderBy('created_at')
            ->offset($this->offset)
            ->limit($this->limit)
            ->get();

        foreach ($items as $item) {
            $this->importItem($session, $item);
        }

        // Update checkpoint
        $this->updateCheckpoint($session);
    }

    private function importItem(MigrationSession $session, MigrationItem $item): void
    {
        $item->update(['status' => 'importing']);

        try {
            $payload = is_string($item->source_payload)
                ? json_decode($item->source_payload, true)
                : $item->source_payload;

            if (! is_array($payload)) {
                throw new \RuntimeException('Invalid source payload');
            }

            $fields = $payload['fields'] ?? [];
            $taxonomyIds = $payload['taxonomy_ids'] ?? [];

            $title = (string) ($fields['title'] ?? $fields['name'] ?? 'Untitled');
            $body = (string) ($fields['body'] ?? $fields['content'] ?? '');
            $excerpt = $fields['excerpt'] ?? $fields['description'] ?? null;
            $slug = (string) ($fields['slug'] ?? Str::slug($title));

            // Find the content type mapping
            $typeMapping = $session->typeMappings()
                ->where('source_type_key', $item->source_type_key)
                ->first();

            /** @var \App\Models\Migration\MigrationTypeMapping|null $typeMapping */
            $contentTypeId = $typeMapping?->numen_content_type_id;

            DB::transaction(function () use ($session, $item, $title, $body, $excerpt, $slug, $contentTypeId, $taxonomyIds, $fields): void {
                $content = Content::create([
                    'space_id' => $session->space_id,
                    'content_type_id' => $contentTypeId,
                    'slug' => $this->ensureUniqueSlug($session->space_id, $slug),
                    'status' => 'draft',
                    'locale' => $session->options['locale'] ?? 'en',
                    'taxonomy' => ! empty($taxonomyIds) ? $taxonomyIds : null,
                    'metadata' => [
                        'migrated_from' => $session->source_cms,
                        'source_id' => $item->source_id,
                        'migration_session_id' => $session->id,
                    ],
                ]);

                $version = ContentVersion::create([
                    'content_id' => $content->id,
                    'version_number' => 1,
                    'status' => 'draft',
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'body' => $body,
                    'body_format' => 'html',
                    'structured_fields' => $fields,
                    'author_type' => 'system',
                    'author_id' => $session->created_by,
                    'change_reason' => 'Imported via migration wizard',
                ]);

                $content->update([
                    'current_version_id' => $version->id,
                    'draft_version_id' => $version->id,
                ]);

                $item->update([
                    'status' => 'completed',
                    'numen_content_id' => $content->id,
                    'error_message' => null,
                ]);
            });

            // Update session counters
            $session->increment('processed_items');
        } catch (\Throwable $e) {
            Log::warning('MigrateContentChunkJob: item import failed', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);

            $item->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 500),
                'attempt' => $item->attempt + 1,
            ]);

            $session->increment('failed_items');
        }
    }

    private function ensureUniqueSlug(string $spaceId, string $slug): string
    {
        $original = $slug;
        $counter = 1;

        while (Content::where('space_id', $spaceId)->where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function updateCheckpoint(MigrationSession $session): void
    {
        $completed = MigrationItem::where('migration_session_id', $session->id)
            ->where('status', 'completed')
            ->count();

        $sourceTypeKeys = MigrationItem::where('migration_session_id', $session->id)
            ->distinct()
            ->pluck('source_type_key');

        foreach ($sourceTypeKeys as $typeKey) {
            $typeCount = MigrationItem::where('migration_session_id', $session->id)
                ->where('source_type_key', $typeKey)
                ->whereIn('status', ['completed', 'failed'])
                ->count();

            MigrationCheckpoint::updateOrCreate(
                [
                    'migration_session_id' => $session->id,
                    'source_type_key' => $typeKey,
                ],
                [
                    'space_id' => $session->space_id,
                    'last_cursor' => (string) $this->offset + $this->limit,
                    'last_synced_at' => now(),
                    'item_count' => $typeCount,
                ],
            );
        }

        // Check if all items are done → update session
        $remaining = MigrationItem::where('migration_session_id', $session->id)
            ->whereIn('status', ['transformed', 'importing'])
            ->count();

        if ($remaining === 0) {
            $failed = MigrationItem::where('migration_session_id', $session->id)
                ->where('status', 'failed')
                ->count();

            $session->update([
                'status' => $failed > 0 && $completed === 0 ? 'failed' : 'completed',
                'completed_at' => now(),
            ]);
        }
    }
}
