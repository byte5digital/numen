<?php

namespace App\Console\Commands\Content;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\Space;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ContentImportCommand extends Command
{
    protected $signature = 'numen:content:import
        {--file= : Path to JSON file to import}
        {--space-id= : Space ID to import content into}
        {--dry-run : Preview what would be imported without persisting}';

    protected $description = 'Import content from a JSON file';

    /** @var string[] */
    private const ALLOWED_STATUSES = ['draft', 'published', 'archived'];

    public function handle(): int
    {
        $filePath = $this->option('file');

        if (! $filePath) {
            $this->error('Please provide a file path using --file.');

            return self::FAILURE;
        }

        // Resolve realpath to detect and reject traversal attacks
        $resolvedPath = realpath($filePath);

        if ($resolvedPath === false) {
            $this->error("File not found or unresolvable path: {$filePath}");

            return self::FAILURE;
        }

        // Reject path traversal: if input differs from resolved path in a suspicious way
        $normalizedInput = str_replace('\\', '/', $filePath);
        if (str_contains($normalizedInput, '../') || str_contains($normalizedInput, './..')) {
            $this->error('Path traversal detected. Use an absolute or clean relative path.');

            return self::FAILURE;
        }

        if (! is_readable($resolvedPath)) {
            $this->error("File is not readable: {$resolvedPath}");

            return self::FAILURE;
        }

        // Warn (but don't block) if file is outside storage_path — CLI is privileged
        $storagePath = realpath(storage_path()) ?: storage_path();
        if (! str_starts_with($resolvedPath, $storagePath)) {
            $this->warn("Note: file is outside storage_path() ({$storagePath}). Proceeding anyway (CLI is trusted).");
        }

        $json = File::get($resolvedPath);
        $items = json_decode($json, true);

        if (! is_array($items)) {
            $this->error('Invalid JSON: expected an array of content objects.');

            return self::FAILURE;
        }

        $spaceId = $this->option('space-id');

        if (! $spaceId) {
            $space = Space::first();

            if (! $space) {
                $this->error('No space found. Please provide --space-id.');

                return self::FAILURE;
            }

            $spaceId = $space->id;
            $this->warn("No --space-id provided; using first space: {$spaceId}");
        }

        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('[DRY RUN] Preview of import — nothing will be written.');
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($items as $index => $item) {
            $slug = $item['slug'] ?? null;

            if (! $slug) {
                $this->warn("Item #{$index}: missing 'slug' — skipped.");
                $skipped++;

                continue;
            }

            $exists = Content::where('slug', $slug)->where('space_id', $spaceId)->exists();

            if ($exists) {
                $this->line("Skipping '{$slug}': already exists.");
                $skipped++;

                continue;
            }

            if ($isDryRun) {
                $this->info("[DRY RUN] Would import: {$slug}");
                $created++;

                continue;
            }

            try {
                DB::transaction(function () use ($item, $spaceId, $slug, &$created): void {
                    $typeSlug = $item['content_type'] ?? 'article';
                    $contentType = ContentType::where('slug', $typeSlug)
                        ->where('space_id', $spaceId)
                        ->first();

                    if (! $contentType) {
                        $contentType = ContentType::where('slug', $typeSlug)->first();
                    }

                    // Whitelist status — default to 'draft' if value is invalid or missing
                    $rawStatus = $item['status'] ?? 'draft';
                    $status = in_array($rawStatus, self::ALLOWED_STATUSES, true) ? $rawStatus : 'draft';

                    if ($status !== $rawStatus) {
                        $this->warn("Item '{$slug}': invalid status '{$rawStatus}' — defaulting to 'draft'.");
                    }

                    $content = Content::create([
                        'space_id' => $spaceId,
                        'content_type_id' => $contentType ? $contentType->id : (ContentType::where('space_id', $spaceId)->first()?->id),
                        'slug' => $slug,
                        'status' => $status,
                        'locale' => $item['locale'] ?? 'en',
                    ]);

                    $version = ContentVersion::create([
                        'content_id' => $content->id,
                        'space_id' => $spaceId,
                        'title' => $item['title'] ?? $slug,
                        'excerpt' => $item['excerpt'] ?? null,
                        'body' => $item['body'] ?? null,
                        'seo_data' => $item['seo_data'] ?? null,
                        'version_number' => 1,
                        'status' => $content->status === 'published' ? 'published' : 'draft',
                        'created_by' => null,
                    ]);

                    $content->update(['current_version_id' => $version->id]);
                    $created++;
                });

                $this->info("Imported: {$slug}");
            } catch (\Throwable $e) {
                $this->error("Failed to import '{$slug}': {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->table(
            ['Created', 'Skipped', 'Failed'],
            [[$created, $skipped, $failed]]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
