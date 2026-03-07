# Content Versioning & Draft System — Architecture Plan

> **Author:** Blueprint 🏗️ (Numen Software Architect)
> **Date:** 2026-03-07
> **Branch:** `feature/versioning`
> **Status:** Architecture Design — Ready for Review
> **Discussion:** GitHub Discussion #6

---

## Overview

This document defines the architecture for Numen's content versioning and draft system. The design builds on the existing `Content → ContentVersion → ContentBlock` hierarchy without modifying any existing migrations. All schema changes are additive.

### Design Goals

1. **Auto-save drafts** without polluting the version history
2. **Named versions** with semantic labels for human reference
3. **Side-by-side diff view** between any two versions
4. **One-click rollback** to any previous version
5. **Scheduled publishing** at a future date/time
6. **Version branches** — edit next version while current is live
7. **AI pipeline versioning** — every pipeline run creates a tracked version with full provenance

### Key Principle

> Versions are immutable snapshots. Drafts are mutable workspaces. Publishing is a pointer swap.

---

## 1. Database Schema

### 1.1 New Migration: Add Versioning Columns to `content_versions`

**File:** `database/migrations/2026_03_07_000001_add_versioning_fields_to_content_versions_table.php`

```php
Schema::table('content_versions', function (Blueprint $table) {
    // Named versions: "v1.0 Launch Copy", "v2.0 SEO Update"
    $table->string('label')->nullable()->after('version_number');

    // Version lifecycle status
    // draft | published | archived | scheduled
    $table->string('status')->default('draft')->after('label');

    // Branch support: which version is this branched from?
    $table->ulid('parent_version_id')->nullable()->after('status');

    // Scheduled publishing
    $table->timestamp('scheduled_at')->nullable()->after('seo_score');

    // Snapshot hash for fast equality checks
    $table->string('content_hash', 64)->nullable()->after('scheduled_at');

    // Soft-lock: who is currently editing this draft?
    $table->ulid('locked_by')->nullable()->after('content_hash');
    $table->timestamp('locked_at')->nullable()->after('locked_by');

    // Foreign keys
    $table->foreign('parent_version_id')
        ->references('id')->on('content_versions')
        ->nullOnDelete();
});
```

### 1.2 New Migration: Add Draft Tracking to `contents`

**File:** `database/migrations/2026_03_07_000002_add_draft_version_id_to_contents_table.php`

```php
Schema::table('contents', function (Blueprint $table) {
    // Points to the active draft (mutable working copy)
    $table->ulid('draft_version_id')->nullable()->after('current_version_id');

    // Scheduled status is already supported via the status enum
    // We add a convenience column for the next scheduled publish time
    $table->timestamp('scheduled_publish_at')->nullable()->after('refresh_at');
});
```

### 1.3 New Table: `content_drafts` (Auto-Save Buffer)

**File:** `database/migrations/2026_03_07_000003_create_content_drafts_table.php`

Auto-save drafts are ephemeral. They don't create versions — they buffer unsaved work.

```php
Schema::create('content_drafts', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->ulid('content_id')->index();
    $table->ulid('user_id')->index();

    // The working data (mirrors ContentVersion fields)
    $table->string('title');
    $table->text('excerpt')->nullable();
    $table->longText('body');
    $table->string('body_format')->default('markdown');
    $table->json('structured_fields')->nullable();
    $table->json('seo_data')->nullable();
    $table->json('blocks_snapshot')->nullable(); // serialized ContentBlock array

    // Which version this draft is based on
    $table->ulid('base_version_id')->nullable();

    // Auto-save metadata
    $table->timestamp('last_saved_at');
    $table->unsignedInteger('save_count')->default(0);

    $table->timestamps();

    $table->unique(['content_id', 'user_id']); // one draft per user per content
    $table->foreign('content_id')->references('id')->on('contents')->cascadeOnDelete();
    $table->foreign('base_version_id')->references('id')->on('content_versions')->nullOnDelete();
});
```

### 1.4 New Table: `scheduled_publishes`

**File:** `database/migrations/2026_03_07_000004_create_scheduled_publishes_table.php`

```php
Schema::create('scheduled_publishes', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->ulid('content_id')->index();
    $table->ulid('version_id');
    $table->ulid('scheduled_by'); // user who scheduled it

    $table->timestamp('publish_at');
    $table->string('status')->default('pending'); // pending | published | cancelled | failed
    $table->text('notes')->nullable();

    $table->timestamps();

    $table->foreign('content_id')->references('id')->on('contents')->cascadeOnDelete();
    $table->foreign('version_id')->references('id')->on('content_versions')->cascadeOnDelete();
});
```

### Entity-Relationship Summary

```
Content
  ├── current_version_id  → ContentVersion (live/published)
  ├── draft_version_id    → ContentVersion (active draft branch)
  ├── versions[]          → ContentVersion (all versions, ordered)
  └── autosaveDraft       → ContentDraft (per-user ephemeral buffer)

ContentVersion
  ├── parent_version_id   → ContentVersion (branch parent)
  ├── blocks[]            → ContentBlock (ordered content blocks)
  ├── pipelineRun         → PipelineRun (AI provenance)
  ├── label               → string (named version)
  ├── status              → draft | published | archived | scheduled
  └── scheduled_at        → timestamp (scheduled publish time)

ContentDraft (auto-save buffer)
  ├── content_id          → Content
  ├── user_id             → User
  └── base_version_id     → ContentVersion (what we're editing from)

ScheduledPublish
  ├── content_id          → Content
  ├── version_id          → ContentVersion
  └── publish_at          → timestamp
```

---

## 2. Models & Relationships

### 2.1 New Model: `ContentDraft`

```php
namespace App\Models;

class ContentDraft extends Model
{
    use HasUlids;

    protected $fillable = [
        'content_id', 'user_id', 'title', 'excerpt', 'body',
        'body_format', 'structured_fields', 'seo_data',
        'blocks_snapshot', 'base_version_id',
        'last_saved_at', 'save_count',
    ];

    protected $casts = [
        'structured_fields' => 'array',
        'seo_data' => 'array',
        'blocks_snapshot' => 'array',
        'last_saved_at' => 'datetime',
    ];

    public function content(): BelongsTo { ... }
    public function user(): BelongsTo { ... }
    public function baseVersion(): BelongsTo { ... }
}
```

### 2.2 New Model: `ScheduledPublish`

```php
namespace App\Models;

class ScheduledPublish extends Model
{
    use HasUlids;

    protected $fillable = [
        'content_id', 'version_id', 'scheduled_by',
        'publish_at', 'status', 'notes',
    ];

    protected $casts = [
        'publish_at' => 'datetime',
    ];

    // Scopes
    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopeDue($q) { return $q->pending()->where('publish_at', '<=', now()); }

    public function content(): BelongsTo { ... }
    public function version(): BelongsTo { ... }
    public function scheduler(): BelongsTo { return $this->belongsTo(User::class, 'scheduled_by'); }
}
```

### 2.3 Updated Model: `ContentVersion` (New Relations & Scopes)

Add to existing model — no breaking changes:

```php
// New relations
public function parentVersion(): BelongsTo
{
    return $this->belongsTo(self::class, 'parent_version_id');
}

public function childVersions(): HasMany
{
    return $this->hasMany(self::class, 'parent_version_id');
}

// New scopes
public function scopePublished($q) { return $q->where('status', 'published'); }
public function scopeDrafts($q) { return $q->where('status', 'draft'); }
public function scopeScheduled($q) { return $q->where('status', 'scheduled'); }
public function scopeLabeled($q) { return $q->whereNotNull('label'); }

// Hash computation for fast diff detection
public function computeHash(): string
{
    $payload = json_encode([
        'title' => $this->title,
        'excerpt' => $this->excerpt,
        'body' => $this->body,
        'structured_fields' => $this->structured_fields,
        'seo_data' => $this->seo_data,
    ]);
    return hash('xxh128', $payload);
}
```

### 2.4 Updated Model: `Content` (New Relations)

Add to existing model:

```php
public function draftVersion(): BelongsTo
{
    return $this->belongsTo(ContentVersion::class, 'draft_version_id');
}

public function autosaveDraft(): HasOne
{
    return $this->hasOne(ContentDraft::class);
}

public function scheduledPublishes(): HasMany
{
    return $this->hasMany(ScheduledPublish::class);
}

public function nextScheduledPublish(): HasOne
{
    return $this->hasOne(ScheduledPublish::class)
        ->pending()
        ->orderBy('publish_at');
}
```

### 2.5 Trait: `HasVersioning`

A reusable trait for models that participate in versioning:

```php
namespace App\Models\Concerns;

trait HasVersioning
{
    public function createVersion(array $data, string $changeReason = null): ContentVersion
    {
        $nextNumber = $this->versions()->max('version_number') + 1;

        return $this->versions()->create(array_merge($data, [
            'version_number' => $nextNumber,
            'change_reason' => $changeReason,
        ]));
    }

    public function rollbackTo(ContentVersion $version): ContentVersion
    {
        $new = $this->createVersion(
            $version->only(['title', 'excerpt', 'body', 'body_format', 'structured_fields', 'seo_data']),
            "Rollback to v{$version->version_number}" . ($version->label ? " ({$version->label})" : ''),
        );

        // Clone blocks
        $version->blocks->each(fn ($block) => $new->blocks()->create(
            $block->only(['type', 'sort_order', 'data', 'wysiwyg_override'])
        ));

        $new->update([
            'parent_version_id' => $version->id,
            'content_hash' => $new->computeHash(),
        ]);

        return $new;
    }
}
```

---

## 3. Service Layer

### 3.1 `VersioningService`

**File:** `app/Services/Versioning/VersioningService.php`

```php
namespace App\Services\Versioning;

class VersioningService
{
    /**
     * Create a new draft version for editing.
     * If content has a live version, branch from it.
     */
    public function createDraft(Content $content, ?ContentVersion $branchFrom = null): ContentVersion
    {
        $base = $branchFrom ?? $content->currentVersion;
        $nextNumber = $content->versions()->max('version_number') + 1;

        $draft = $content->versions()->create([
            'version_number' => $nextNumber,
            'title' => $base?->title ?? '',
            'excerpt' => $base?->excerpt,
            'body' => $base?->body ?? '',
            'body_format' => $base?->body_format ?? 'markdown',
            'structured_fields' => $base?->structured_fields,
            'seo_data' => $base?->seo_data,
            'author_type' => 'human',
            'author_id' => auth()->id(),
            'status' => 'draft',
            'parent_version_id' => $base?->id,
            'change_reason' => $branchFrom ? "Branched from v{$base->version_number}" : null,
        ]);

        // Clone blocks from base version
        if ($base) {
            $base->blocks->each(fn ($block) => $draft->blocks()->create(
                $block->only(['type', 'sort_order', 'data', 'wysiwyg_override'])
            ));
        }

        $content->update(['draft_version_id' => $draft->id]);

        return $draft;
    }

    /**
     * Auto-save draft content (debounced, no version creation).
     */
    public function autoSave(Content $content, User $user, array $data): ContentDraft
    {
        return ContentDraft::updateOrCreate(
            ['content_id' => $content->id, 'user_id' => $user->id],
            array_merge($data, [
                'last_saved_at' => now(),
                'save_count' => DB::raw('save_count + 1'),
            ])
        );
    }

    /**
     * Promote a draft to a named version (save point).
     */
    public function saveVersion(
        ContentVersion $draft,
        string $label,
        ?string $changeReason = null
    ): ContentVersion {
        $draft->update([
            'label' => $label,
            'change_reason' => $changeReason ?? $draft->change_reason,
            'content_hash' => $draft->computeHash(),
        ]);

        // Clear auto-save buffer for this content
        ContentDraft::where('content_id', $draft->content_id)->delete();

        return $draft->fresh();
    }

    /**
     * Publish a specific version (makes it live).
     */
    public function publish(Content $content, ContentVersion $version): void
    {
        // Archive previously published version
        $content->versions()
            ->where('status', 'published')
            ->update(['status' => 'archived']);

        $version->update(['status' => 'published']);

        $content->update([
            'current_version_id' => $version->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        // If the published version was the draft, clear draft pointer
        if ($content->draft_version_id === $version->id) {
            $content->update(['draft_version_id' => null]);
        }

        event(new ContentPublished($content, $version));
    }

    /**
     * Schedule a version for future publishing.
     */
    public function schedule(
        Content $content,
        ContentVersion $version,
        Carbon $publishAt,
        ?string $notes = null
    ): ScheduledPublish {
        // Cancel any existing pending schedules for this content
        $content->scheduledPublishes()->pending()->update(['status' => 'cancelled']);

        $version->update([
            'status' => 'scheduled',
            'scheduled_at' => $publishAt,
        ]);

        $content->update([
            'status' => 'scheduled',
            'scheduled_publish_at' => $publishAt,
        ]);

        $schedule = ScheduledPublish::create([
            'content_id' => $content->id,
            'version_id' => $version->id,
            'scheduled_by' => auth()->id(),
            'publish_at' => $publishAt,
            'notes' => $notes,
        ]);

        // Dispatch delayed job
        PublishScheduledContent::dispatch($schedule->id)
            ->delay($publishAt);

        return $schedule;
    }

    /**
     * Rollback: create a new version from a historical one and publish it.
     */
    public function rollback(Content $content, ContentVersion $targetVersion): ContentVersion
    {
        $newVersion = $content->createVersion(
            array_merge(
                $targetVersion->only([
                    'title', 'excerpt', 'body', 'body_format',
                    'structured_fields', 'seo_data',
                ]),
                [
                    'author_type' => 'human',
                    'author_id' => auth()->id(),
                    'status' => 'draft',
                    'parent_version_id' => $targetVersion->id,
                ]
            ),
            "Rollback to v{$targetVersion->version_number}" .
                ($targetVersion->label ? " ({$targetVersion->label})" : ''),
        );

        // Clone blocks
        $targetVersion->blocks->each(fn ($block) => $newVersion->blocks()->create(
            $block->only(['type', 'sort_order', 'data', 'wysiwyg_override'])
        ));

        $newVersion->update(['content_hash' => $newVersion->computeHash()]);

        // Auto-publish the rollback
        $this->publish($content, $newVersion);

        return $newVersion;
    }

    /**
     * Compare two versions and return structured diff.
     */
    public function diff(ContentVersion $versionA, ContentVersion $versionB): VersionDiff
    {
        return app(DiffEngine::class)->compare($versionA, $versionB);
    }

    /**
     * Create a branch: new draft from a non-current version.
     * Enables "work on next version while current is live".
     */
    public function branch(Content $content, ContentVersion $fromVersion, ?string $label = null): ContentVersion
    {
        $draft = $this->createDraft($content, $fromVersion);

        if ($label) {
            $draft->update(['label' => $label]);
        }

        return $draft;
    }
}
```

### 3.2 `DiffEngine`

**File:** `app/Services/Versioning/DiffEngine.php`

```php
namespace App\Services\Versioning;

class DiffEngine
{
    /**
     * Compare two ContentVersions and produce a VersionDiff.
     */
    public function compare(ContentVersion $a, ContentVersion $b): VersionDiff
    {
        return new VersionDiff(
            versionA: $a,
            versionB: $b,
            fieldDiffs: $this->diffFields($a, $b),
            blockDiffs: $this->diffBlocks($a, $b),
            seoDiffs: $this->diffSeoData($a->seo_data ?? [], $b->seo_data ?? []),
        );
    }

    /**
     * Diff scalar fields: title, excerpt, body.
     * Uses line-level diff for body, word-level for title/excerpt.
     */
    private function diffFields(ContentVersion $a, ContentVersion $b): array
    {
        $diffs = [];

        foreach (['title', 'excerpt'] as $field) {
            if ($a->$field !== $b->$field) {
                $diffs[$field] = [
                    'type' => 'changed',
                    'old' => $a->$field,
                    'new' => $b->$field,
                    'hunks' => $this->wordDiff($a->$field ?? '', $b->$field ?? ''),
                ];
            }
        }

        if ($a->body !== $b->body) {
            $diffs['body'] = [
                'type' => 'changed',
                'hunks' => $this->lineDiff($a->body, $b->body),
                'stats' => $this->diffStats($a->body, $b->body),
            ];
        }

        return $diffs;
    }

    /**
     * Diff content blocks by matching on sort_order + type.
     * Returns added, removed, modified, reordered blocks.
     */
    private function diffBlocks(ContentVersion $a, ContentVersion $b): array
    {
        $blocksA = $a->blocks->keyBy('sort_order')->toArray();
        $blocksB = $b->blocks->keyBy('sort_order')->toArray();

        $allKeys = array_unique(array_merge(array_keys($blocksA), array_keys($blocksB)));
        sort($allKeys);

        $diffs = [];
        foreach ($allKeys as $key) {
            $inA = isset($blocksA[$key]);
            $inB = isset($blocksB[$key]);

            if ($inA && !$inB) {
                $diffs[] = ['type' => 'removed', 'position' => $key, 'block' => $blocksA[$key]];
            } elseif (!$inA && $inB) {
                $diffs[] = ['type' => 'added', 'position' => $key, 'block' => $blocksB[$key]];
            } elseif ($blocksA[$key]['data'] !== $blocksB[$key]['data']
                      || $blocksA[$key]['type'] !== $blocksB[$key]['type']) {
                $diffs[] = [
                    'type' => 'modified',
                    'position' => $key,
                    'old' => $blocksA[$key],
                    'new' => $blocksB[$key],
                ];
            }
        }

        return $diffs;
    }

    /**
     * Line-level diff using patience algorithm (via jfcherng/php-diff).
     */
    private function lineDiff(string $a, string $b): array
    {
        // Uses jfcherng/php-diff for unified/side-by-side output
        $differ = new \Jfcherng\Diff\Differ(
            explode("\n", $a),
            explode("\n", $b),
            ['context' => 3]
        );
        return $differ->getGroupedOpcodes();
    }

    private function wordDiff(string $a, string $b): array { /* word-level tokenization + LCS */ }

    private function diffStats(string $a, string $b): array
    {
        $linesA = substr_count($a, "\n") + 1;
        $linesB = substr_count($b, "\n") + 1;
        return [
            'lines_added' => max(0, $linesB - $linesA),
            'lines_removed' => max(0, $linesA - $linesB),
            'words_old' => str_word_count($a),
            'words_new' => str_word_count($b),
        ];
    }

    private function diffSeoData(array $a, array $b): array
    {
        $diffs = [];
        $allKeys = array_unique(array_merge(array_keys($a), array_keys($b)));
        foreach ($allKeys as $key) {
            if (($a[$key] ?? null) !== ($b[$key] ?? null)) {
                $diffs[$key] = ['old' => $a[$key] ?? null, 'new' => $b[$key] ?? null];
            }
        }
        return $diffs;
    }
}
```

### 3.3 Value Object: `VersionDiff`

```php
namespace App\Services\Versioning;

class VersionDiff implements \JsonSerializable
{
    public function __construct(
        public readonly ContentVersion $versionA,
        public readonly ContentVersion $versionB,
        public readonly array $fieldDiffs,
        public readonly array $blockDiffs,
        public readonly array $seoDiffs,
    ) {}

    public function hasChanges(): bool
    {
        return !empty($this->fieldDiffs)
            || !empty($this->blockDiffs)
            || !empty($this->seoDiffs);
    }

    public function summary(): string
    {
        $parts = [];
        if (isset($this->fieldDiffs['title'])) $parts[] = 'title changed';
        if (isset($this->fieldDiffs['body'])) {
            $stats = $this->fieldDiffs['body']['stats'] ?? [];
            $parts[] = sprintf('body: +%d/-%d lines', $stats['lines_added'] ?? 0, $stats['lines_removed'] ?? 0);
        }
        $added = count(array_filter($this->blockDiffs, fn ($d) => $d['type'] === 'added'));
        $removed = count(array_filter($this->blockDiffs, fn ($d) => $d['type'] === 'removed'));
        if ($added) $parts[] = "$added blocks added";
        if ($removed) $parts[] = "$removed blocks removed";
        if (!empty($this->seoDiffs)) $parts[] = count($this->seoDiffs) . ' SEO fields changed';

        return implode(', ', $parts) ?: 'No changes';
    }

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
```

---

## 4. API Endpoints

### 4.1 Admin API Routes

**File:** `routes/api.php` (additions inside `auth:sanctum` middleware group)

```php
// Version management
Route::prefix('content/{content}')->group(function () {
    // List all versions
    Route::get('/versions', [VersionController::class, 'index']);

    // Get specific version
    Route::get('/versions/{version}', [VersionController::class, 'show']);

    // Create new draft
    Route::post('/versions/draft', [VersionController::class, 'createDraft']);

    // Update draft content
    Route::put('/versions/{version}', [VersionController::class, 'update']);

    // Auto-save (debounced endpoint)
    Route::post('/autosave', [AutoSaveController::class, 'save']);

    // Get auto-save draft
    Route::get('/autosave', [AutoSaveController::class, 'show']);

    // Discard auto-save
    Route::delete('/autosave', [AutoSaveController::class, 'discard']);

    // Name/label a version
    Route::patch('/versions/{version}/label', [VersionController::class, 'label']);

    // Publish a version
    Route::post('/versions/{version}/publish', [VersionController::class, 'publish']);

    // Schedule a version
    Route::post('/versions/{version}/schedule', [VersionController::class, 'schedule']);

    // Cancel scheduled publish
    Route::delete('/versions/{version}/schedule', [VersionController::class, 'cancelSchedule']);

    // Rollback to a version
    Route::post('/versions/{version}/rollback', [VersionController::class, 'rollback']);

    // Branch from a version
    Route::post('/versions/{version}/branch', [VersionController::class, 'branch']);

    // Compare two versions
    Route::get('/diff', [DiffController::class, 'compare']);
    // Query params: ?version_a={id}&version_b={id}
});
```

### 4.2 Controller: `VersionController`

```php
namespace App\Http\Controllers\Api;

class VersionController extends Controller
{
    public function __construct(private VersioningService $versioning) {}

    public function index(Content $content): JsonResponse
    {
        $versions = $content->versions()
            ->select(['id', 'version_number', 'label', 'status', 'author_type',
                       'author_id', 'change_reason', 'pipeline_run_id',
                       'quality_score', 'seo_score', 'scheduled_at', 'created_at'])
            ->with('pipelineRun:id,status')
            ->paginate(25);

        return response()->json($versions);
    }

    public function show(Content $content, ContentVersion $version): JsonResponse
    {
        $version->load(['blocks', 'pipelineRun', 'parentVersion:id,version_number,label']);
        return response()->json(['data' => $version]);
    }

    public function createDraft(Content $content): JsonResponse
    {
        $draft = $this->versioning->createDraft($content);
        return response()->json(['data' => $draft], 201);
    }

    public function update(Content $content, ContentVersion $version, UpdateVersionRequest $request): JsonResponse
    {
        abort_unless($version->status === 'draft', 422, 'Only draft versions can be edited.');
        $version->update($request->validated());
        return response()->json(['data' => $version->fresh()]);
    }

    public function label(Content $content, ContentVersion $version, Request $request): JsonResponse
    {
        $request->validate(['label' => 'required|string|max:255']);
        $this->versioning->saveVersion($version, $request->label);
        return response()->json(['data' => $version->fresh()]);
    }

    public function publish(Content $content, ContentVersion $version): JsonResponse
    {
        $this->versioning->publish($content, $version);
        return response()->json(['message' => 'Published', 'data' => $content->fresh()]);
    }

    public function schedule(Content $content, ContentVersion $version, Request $request): JsonResponse
    {
        $request->validate([
            'publish_at' => 'required|date|after:now',
            'notes' => 'nullable|string|max:500',
        ]);

        $schedule = $this->versioning->schedule(
            $content, $version,
            Carbon::parse($request->publish_at),
            $request->notes,
        );

        return response()->json(['data' => $schedule], 201);
    }

    public function cancelSchedule(Content $content, ContentVersion $version): JsonResponse
    {
        $content->scheduledPublishes()->pending()->update(['status' => 'cancelled']);
        $version->update(['status' => 'draft', 'scheduled_at' => null]);
        $content->update(['status' => 'draft', 'scheduled_publish_at' => null]);

        return response()->json(['message' => 'Schedule cancelled']);
    }

    public function rollback(Content $content, ContentVersion $version): JsonResponse
    {
        $newVersion = $this->versioning->rollback($content, $version);
        return response()->json(['data' => $newVersion], 201);
    }

    public function branch(Content $content, ContentVersion $version, Request $request): JsonResponse
    {
        $draft = $this->versioning->branch($content, $version, $request->label);
        return response()->json(['data' => $draft], 201);
    }
}
```

### 4.3 Controller: `DiffController`

```php
class DiffController extends Controller
{
    public function compare(Content $content, Request $request, VersioningService $versioning): JsonResponse
    {
        $request->validate([
            'version_a' => 'required|exists:content_versions,id',
            'version_b' => 'required|exists:content_versions,id',
        ]);

        $a = ContentVersion::with('blocks')->findOrFail($request->version_a);
        $b = ContentVersion::with('blocks')->findOrFail($request->version_b);

        // Ensure both versions belong to this content
        abort_unless($a->content_id === $content->id && $b->content_id === $content->id, 422);

        $diff = $versioning->diff($a, $b);

        return response()->json(['data' => $diff]);
    }
}
```

---

## 5. Admin UI Components (Vue 3 / Inertia.js)

### 5.1 Component Hierarchy

```
ContentEditor/
├── VersionSidebar.vue          — version history panel (right sidebar)
│   ├── VersionListItem.vue     — single version row (number, label, status, date)
│   ├── VersionBadge.vue        — status badge (draft/published/scheduled/archived)
│   └── VersionActions.vue      — publish/schedule/rollback/branch buttons
├── AutoSaveIndicator.vue       — "Saving..." / "Saved 2s ago" / "Unsaved changes"
├── VersionDiffModal.vue        — full-screen modal for diff view
│   ├── DiffSelector.vue        — dropdowns to pick version A vs B
│   ├── SideBySideDiff.vue      — two-column diff with highlighted changes
│   ├── UnifiedDiff.vue         — single-column unified diff (toggle)
│   ├── BlockDiffView.vue       — visual block-level diff
│   └── SeoDiffPanel.vue        — SEO metadata diff
├── SchedulePublishModal.vue    — date/time picker for scheduled publishing
├── VersionLabelModal.vue       — name a version dialog
└── BranchIndicator.vue         — shows "Editing v3 (branched from v2)"
```

### 5.2 Key Component Details

#### `VersionSidebar.vue`
- Fetches `GET /api/v1/content/{id}/versions` on mount
- Shows scrollable timeline of versions
- Each version shows: number, label (if any), status badge, author, timestamp
- Pipeline-generated versions show 🤖 icon + pipeline run link
- Click a version to preview it; action buttons for publish/rollback/branch/diff

#### `AutoSaveIndicator.vue`
- Watches form state via debounce (2 second delay)
- Posts to `POST /api/v1/content/{id}/autosave` on change
- Shows status: "Saving..." → "Saved" → "Unsaved changes"
- On page load, checks `GET /api/v1/content/{id}/autosave` for recovery
- If auto-save exists newer than current draft, prompts: "Recover unsaved changes?"

#### `VersionDiffModal.vue`
- Two dropdown selectors for version A (left) and version B (right)
- Fetches `GET /api/v1/content/{id}/diff?version_a=X&version_b=Y`
- Toggle between side-by-side and unified diff views
- Syntax-highlighted diff for markdown body
- Visual block diff showing added/removed/modified blocks with type icons
- Summary stats bar: "+12 lines, -3 lines, 2 blocks added"

#### `SchedulePublishModal.vue`
- Date picker + time picker (with timezone display)
- Optional notes field
- Shows current timezone and UTC conversion
- Confirmation step showing "This version will go live on [date]"

---

## 6. Diff Engine — Implementation Details

### 6.1 Library Choice

**Primary:** [`jfcherng/php-diff`](https://github.com/jfcherng/php-diff) (MIT license, actively maintained)

- Supports unified, context, and side-by-side output
- JSON-serializable opcodes for API transport
- Line-level and word-level granularity

### 6.2 Diff Strategy by Content Type

| Field | Diff Algorithm | Granularity |
|-------|---------------|-------------|
| `title` | Word-level | Inline spans |
| `excerpt` | Word-level | Inline spans |
| `body` (markdown) | Line-level (patience) | Unified hunks |
| `body` (HTML) | DOM-aware diff | Tag-level |
| `structured_fields` | JSON deep diff | Key-path level |
| `seo_data` | JSON deep diff | Key-path level |
| `blocks[]` | Positional matching | Block-level |

### 6.3 Block Diffing Algorithm

```
1. Index blocks by (sort_order, type) tuple
2. Match blocks across versions using LCS on type sequence
3. For matched blocks: deep-diff the `data` JSON
4. Unmatched in A = removed, unmatched in B = added
5. Matched with different sort_order = reordered
```

### 6.4 Frontend Rendering

The API returns structured diff data (not pre-rendered HTML). The Vue components handle rendering:

- **Side-by-side:** Two columns, synchronized scrolling, highlighted additions (green) and deletions (red)
- **Unified:** Single column, interleaved additions/deletions with context lines
- **Block diff:** Visual cards showing block type icon + inline field diffs

---

## 7. Scheduled Publishing

### 7.1 Job: `PublishScheduledContent`

**File:** `app/Jobs/PublishScheduledContent.php`

```php
namespace App\Jobs;

class PublishScheduledContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public string $scheduleId) {}

    public function handle(VersioningService $versioning): void
    {
        $schedule = ScheduledPublish::with(['content', 'version'])->find($this->scheduleId);

        if (!$schedule || $schedule->status !== 'pending') {
            return; // Already cancelled or published
        }

        try {
            $versioning->publish($schedule->content, $schedule->version);
            $schedule->update(['status' => 'published']);
        } catch (\Throwable $e) {
            $schedule->update(['status' => 'failed']);
            throw $e; // Let queue retry
        }
    }
}
```

### 7.2 Scheduler Backup (Kernel)

In case the delayed job doesn't fire (worker restart, etc.), add a safety net:

**File:** `app/Console/Kernel.php` (addition)

```php
$schedule->command('numen:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();
```

**Artisan Command:** `app/Console/Commands/PublishScheduledCommand.php`

```php
class PublishScheduledCommand extends Command
{
    protected $signature = 'numen:publish-scheduled';
    protected $description = 'Publish any content past its scheduled time';

    public function handle(VersioningService $versioning): int
    {
        $due = ScheduledPublish::due()->with(['content', 'version'])->get();

        foreach ($due as $schedule) {
            try {
                $versioning->publish($schedule->content, $schedule->version);
                $schedule->update(['status' => 'published']);
                $this->info("Published: {$schedule->content->slug} v{$schedule->version->version_number}");
            } catch (\Throwable $e) {
                $schedule->update(['status' => 'failed']);
                $this->error("Failed: {$schedule->content->slug} — {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
```

### 7.3 Reliability

- **Delayed dispatch** is the primary mechanism (fires at exact time if worker is running)
- **Cron safety net** catches anything missed (runs every minute)
- **Idempotent** — both paths check `status === 'pending'` before acting
- **Retries** — job retries 3x with 60s backoff on failure
- **Cancellation** — setting status to `cancelled` prevents either path from publishing

---

## 8. Pipeline Integration

### 8.1 How Pipeline Runs Create Versions

The existing pipeline system creates `ContentVersion` records via `PipelineRun`. We extend this to integrate with the versioning system:

```php
// In the pipeline completion handler (e.g., PublishContent job or stage completion)

class PipelineVersioningIntegration
{
    public function onPipelineComplete(PipelineRun $run, array $generatedContent): ContentVersion
    {
        $content = $run->content;
        $nextNumber = $content->versions()->max('version_number') + 1;

        $version = $content->versions()->create([
            'version_number' => $nextNumber,
            'title' => $generatedContent['title'],
            'excerpt' => $generatedContent['excerpt'] ?? null,
            'body' => $generatedContent['body'],
            'body_format' => $generatedContent['body_format'] ?? 'markdown',
            'structured_fields' => $generatedContent['structured_fields'] ?? null,
            'seo_data' => $generatedContent['seo_data'] ?? null,
            'author_type' => 'ai_agent',
            'author_id' => $run->pipeline->id,
            'change_reason' => "Pipeline run: {$run->pipeline->name}",
            'pipeline_run_id' => $run->id,
            'ai_metadata' => [
                'pipeline_id' => $run->pipeline_id,
                'pipeline_run_id' => $run->id,
                'stages_completed' => array_keys($run->stage_results ?? []),
                'models_used' => $this->extractModelsUsed($run),
                'total_tokens' => $this->extractTotalTokens($run),
                'total_cost_usd' => $this->extractTotalCost($run),
                'brief_id' => $run->content_brief_id,
                'generated_at' => now()->toIso8601String(),
            ],
            'quality_score' => $generatedContent['quality_score'] ?? null,
            'seo_score' => $generatedContent['seo_score'] ?? null,
            'status' => 'draft', // AI versions start as draft, need human approval
            'parent_version_id' => $content->current_version_id,
            'content_hash' => null, // computed after blocks are created
        ]);

        // Create content blocks if pipeline produced them
        if (!empty($generatedContent['blocks'])) {
            foreach ($generatedContent['blocks'] as $i => $block) {
                $version->blocks()->create([
                    'type' => $block['type'],
                    'sort_order' => $i,
                    'data' => $block['data'] ?? null,
                ]);
            }
        }

        // Compute and store content hash
        $version->update(['content_hash' => $version->computeHash()]);

        return $version;
    }

    private function extractModelsUsed(PipelineRun $run): array
    {
        return $run->generationLogs->pluck('model')->unique()->values()->toArray();
    }

    private function extractTotalTokens(PipelineRun $run): int
    {
        return $run->generationLogs->sum('total_tokens');
    }

    private function extractTotalCost(PipelineRun $run): float
    {
        return (float) $run->generationLogs->sum('cost_usd');
    }
}
```

### 8.2 AI Provenance Chain

Every pipeline-generated version maintains a complete provenance chain:

```
ContentVersion
  → pipeline_run_id    → PipelineRun
      → stage_results  → {content_creation: {...}, seo: {...}, editorial: {...}}
      → generationLogs → AIGenerationLog[] (per-stage token/cost/model details)
      → brief          → ContentBrief (original intent)
  → ai_metadata        → {models_used, total_tokens, total_cost_usd, ...}
  → parent_version_id  → previous ContentVersion (what it replaced)
  → content_hash       → deterministic hash (detect identical regeneration)
```

### 8.3 Pipeline Auto-Publish vs Draft Modes

```php
// Config option: numen.pipeline.auto_publish (default: false)

// If auto_publish is enabled AND pipeline has approval stage:
if (config('numen.pipeline.auto_publish') && $run->hasApprovalStage()) {
    $versioning->publish($content, $version);
}

// Otherwise: version stays as draft, shows in admin for human review
// Admin sees: "🤖 AI generated version waiting for review"
```

---

## 9. Events

New events for the versioning system:

```php
// app/Events/
ContentVersionCreated::class    // {content, version, source: 'human'|'pipeline'|'rollback'}
ContentPublished::class         // {content, version, previous_version}
ContentScheduled::class         // {content, version, publish_at}
ContentRolledBack::class        // {content, new_version, target_version}
ContentDraftAutoSaved::class    // {content, user, draft}
```

These integrate with the existing webhook system — frontends can subscribe to publish/schedule events for cache invalidation.

---

## 10. Migration Execution Order

```
2026_03_07_000001_add_versioning_fields_to_content_versions_table.php
2026_03_07_000002_add_draft_version_id_to_contents_table.php
2026_03_07_000003_create_content_drafts_table.php
2026_03_07_000004_create_scheduled_publishes_table.php
```

All migrations are additive. No existing tables or columns are modified. Existing data is fully compatible — all new columns are nullable or have safe defaults.

---

## 11. Dependencies

### New Composer Packages

| Package | Purpose | License |
|---------|---------|---------|
| `jfcherng/php-diff` | Line/word-level diff computation | MIT |

### New NPM Packages

| Package | Purpose |
|---------|---------|
| `diff2html` | Render unified/side-by-side diffs in Vue | MIT |

---

## 12. Trade-offs & Decisions

### Why separate `content_drafts` table instead of using `ContentVersion` for auto-save?

**Decision:** Ephemeral auto-saves should not pollute version history. A user making 50 keystrokes shouldn't create 50 versions. The `content_drafts` table is a per-user mutable buffer that gets promoted to a proper version on explicit save.

### Why version branches via `parent_version_id` instead of a separate branches table?

**Decision:** Numen is a CMS, not Git. We need simple "edit next while current is live" — not full branch/merge semantics. A `parent_version_id` pointer on `ContentVersion` is sufficient and keeps the model simple. If we need full branching later (unlikely for CMS use-cases), we can add a `branches` table in a future minor version.

### Why both delayed job AND cron for scheduled publishing?

**Decision:** Delayed jobs fire at the exact scheduled time but can be lost if the queue worker restarts. The cron job is a safety net that runs every minute. Both are idempotent — the first one to run wins, the other is a no-op.

### Why `content_hash` on versions?

**Decision:** Fast equality checks. When a pipeline regenerates content that's identical to the current version, we can detect it instantly via hash comparison and skip creating a duplicate version.

---

## 13. Implementation Phases

### Phase 1: Core Versioning (Week 1)
- [ ] Migrations (4 files)
- [ ] Models: `ContentDraft`, `ScheduledPublish`
- [ ] Model updates: `Content`, `ContentVersion`
- [ ] `VersioningService` (create, publish, rollback)
- [ ] `VersionController` + routes
- [ ] Tests

### Phase 2: Diff Engine (Week 2)
- [ ] `DiffEngine` + `VersionDiff`
- [ ] `DiffController` + routes
- [ ] Install `jfcherng/php-diff`
- [ ] Vue: `VersionDiffModal`, `SideBySideDiff`, `UnifiedDiff`
- [ ] Tests

### Phase 3: Auto-Save & Scheduling (Week 3)
- [ ] `AutoSaveController`
- [ ] `AutoSaveIndicator.vue`
- [ ] `PublishScheduledContent` job
- [ ] `PublishScheduledCommand`
- [ ] `SchedulePublishModal.vue`
- [ ] Tests

### Phase 4: Pipeline Integration & UI Polish (Week 4)
- [ ] `PipelineVersioningIntegration`
- [ ] `VersionSidebar.vue` with full timeline
- [ ] `BranchIndicator.vue`
- [ ] Events + webhook integration
- [ ] E2E tests

---

*This architecture is designed for Numen 0.x — all additions are backwards compatible and can be iterated on before the 1.0 stability promise.*
