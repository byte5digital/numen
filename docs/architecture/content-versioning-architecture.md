# Content Versioning & Drafts — Architecture Document

> **Author:** Blueprint 🏗️ (numen-architect)
> **Date:** 2026-03-07
> **Status:** Proposal
> **Scope:** Medium — extends existing ContentVersion model, adds UI
> **Branch:** `feature/versioning`

---

## 1. Problem Statement

Content currently transitions from draft → published in a single step. There is no version history browsing, no auto-save drafts, no rollback, no diff view, and no scheduled publishing. The existing `ContentVersion` model tracks versions created by the AI pipeline, but the system lacks:

1. **Auto-save drafts** — editors lose work if they navigate away
2. **Named versions** — no way to label meaningful milestones ("v1.0 Launch Copy")
3. **Diff view** — no comparison between versions
4. **One-click rollback** — can't restore a previous version
5. **Scheduled publishing** — no "publish at 9am Monday"
6. **Version branches** — can't work on a next version while current is live
7. **AI provenance** — pipeline stages create versions but lack granular provenance tracking

## 2. Existing Schema Analysis

### Content Model (`contents` table)
- Has `current_version_id` (nullable ULID) — points to the active version
- Has `status`: draft | in_pipeline | review | scheduled | published | archived
- Has `published_at`, `expires_at`, `refresh_at` timestamps

### ContentVersion Model (`content_versions` table)
- Linked to `content_id` with `version_number` (unique pair)
- Stores: title, excerpt, body, body_format, structured_fields, seo_data
- Tracks: author_type (ai_agent | human), author_id, change_reason
- Links to pipeline_run_id, has ai_metadata, quality_score, seo_score
- Has many `ContentBlock` records (block-based content)

### Key Insight
The existing `ContentVersion` model is already a solid foundation. We need to **extend** it, not replace it.

## 3. Architecture Design

### 3.1 Database Schema Changes (New Migrations Only)

#### Migration 1: `add_versioning_fields_to_content_versions_table`

Adds draft/branch/label/scheduling support to the existing `content_versions` table.

```php
Schema::table('content_versions', function (Blueprint $table) {
    // Version identity & labeling
    $table->string('label')->nullable()->after('version_number');         // "v1.0 Launch Copy"
    $table->boolean('is_draft')->default(false)->after('label');          // Auto-save draft flag
    $table->string('branch')->default('main')->after('is_draft');        // Version branch

    // Publishing schedule
    $table->timestamp('scheduled_publish_at')->nullable()->after('seo_score');

    // Provenance chain
    $table->ulid('parent_version_id')->nullable()->after('pipeline_run_id');  // Which version this was derived from
    $table->string('pipeline_stage')->nullable()->after('parent_version_id'); // Which stage created this

    // Indexes
    $table->index(['content_id', 'branch', 'is_draft']);
    $table->index('scheduled_publish_at');
    $table->foreign('parent_version_id')->references('id')->on('content_versions')->nullOnDelete();
});
```

#### Migration 2: `add_draft_version_id_to_contents_table`

Adds a pointer to the current working draft.

```php
Schema::table('contents', function (Blueprint $table) {
    $table->ulid('draft_version_id')->nullable()->after('current_version_id');
    // Note: foreign key to content_versions, but added without constraint
    // to avoid circular dependency issues during seeding
});
```

#### Migration 3: `create_version_schedules_table`

Dedicated table for scheduled publish jobs (more robust than a timestamp on the version).

```php
Schema::create('version_schedules', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->ulid('content_id');
    $table->ulid('content_version_id');
    $table->ulid('scheduled_by');                  // User ID
    $table->timestamp('publish_at');
    $table->string('status')->default('pending');   // pending | published | cancelled
    $table->timestamps();

    $table->foreign('content_id')->references('id')->on('contents')->cascadeOnDelete();
    $table->foreign('content_version_id')->references('id')->on('content_versions')->cascadeOnDelete();
    $table->foreign('scheduled_by')->references('id')->on('users');
    $table->index(['status', 'publish_at']);
});
```

### 3.2 Model Changes

#### ContentVersion — Extended

```php
// New relationships
public function parentVersion(): BelongsTo
{
    return $this->belongsTo(self::class, 'parent_version_id');
}

public function childVersions(): HasMany
{
    return $this->hasMany(self::class, 'parent_version_id');
}

public function schedule(): HasOne
{
    return $this->hasOne(VersionSchedule::class);
}

// New scopes
public function scopeDrafts($query)
{
    return $query->where('is_draft', true);
}

public function scopePublished($query)
{
    return $query->where('is_draft', false);
}

public function scopeOnBranch($query, string $branch = 'main')
{
    return $query->where('branch', $branch);
}

// New methods
public function isDraft(): bool
{
    return $this->is_draft;
}

public function promote(): self
{
    $this->update(['is_draft' => false]);
    return $this;
}

public function hasLabel(): bool
{
    return $this->label !== null;
}

/**
 * Get the provenance chain back to the original version.
 * Returns versions from newest to oldest.
 */
public function provenanceChain(): Collection
{
    $chain = collect([$this]);
    $current = $this;
    
    while ($current->parent_version_id) {
        $current = $current->parentVersion;
        $chain->push($current);
    }
    
    return $chain;
}
```

#### Content — Extended

```php
// New relationship
public function draftVersion(): BelongsTo
{
    return $this->belongsTo(ContentVersion::class, 'draft_version_id');
}

public function schedules(): HasManyThrough
{
    return $this->hasManyThrough(VersionSchedule::class, ContentVersion::class);
}

// New helpers
public function hasWorkingDraft(): bool
{
    return $this->draft_version_id !== null;
}

public function latestDraft(): ?ContentVersion
{
    return $this->versions()
        ->where('is_draft', true)
        ->latest()
        ->first();
}

public function publishedVersions(): HasMany
{
    return $this->versions()->where('is_draft', false);
}

public function rollbackTo(ContentVersion $version): void
{
    $this->update(['current_version_id' => $version->id]);
}
```

#### New Model: VersionSchedule

```php
class VersionSchedule extends Model
{
    use HasUlids;

    protected $fillable = [
        'content_id', 'content_version_id', 'scheduled_by',
        'publish_at', 'status',
    ];

    protected $casts = [
        'publish_at' => 'datetime',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'content_version_id');
    }

    public function scheduledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }
}
```

### 3.3 Service Layer

#### `App\Services\VersioningService`

Central service for all versioning operations. Keeps controllers thin.

```php
namespace App\Services;

class VersioningService
{
    /**
     * Create a new draft from the current version (auto-save).
     * If a draft already exists for this content, update it instead.
     */
    public function saveDraft(Content $content, array $data, ?User $user = null): ContentVersion;

    /**
     * Promote a draft to a full version (finalize).
     * Assigns the next version_number and clears is_draft.
     */
    public function promoteDraft(ContentVersion $draft, ?string $label = null): ContentVersion;

    /**
     * Create a named version from current state.
     */
    public function createNamedVersion(
        Content $content,
        string $label,
        ?string $changeReason = null
    ): ContentVersion;

    /**
     * Rollback content to a specific version.
     * Creates a new version that copies the target version's data.
     * Does NOT delete history — rollback is additive.
     */
    public function rollback(Content $content, ContentVersion $targetVersion, User $user): ContentVersion;

    /**
     * Schedule a version for future publishing.
     */
    public function schedulePublish(
        Content $content,
        ContentVersion $version,
        Carbon $publishAt,
        User $user
    ): VersionSchedule;

    /**
     * Cancel a scheduled publish.
     */
    public function cancelSchedule(VersionSchedule $schedule): void;

    /**
     * Execute a scheduled publish (called by the job).
     */
    public function executeScheduledPublish(VersionSchedule $schedule): void;

    /**
     * Compare two versions and return a structured diff.
     */
    public function diff(ContentVersion $from, ContentVersion $to): VersionDiff;

    /**
     * Create a version from a pipeline stage result.
     * Tracks provenance: parent version, stage name, AI metadata.
     */
    public function createFromPipelineStage(
        PipelineRun $run,
        string $stageName,
        array $data,
        ?ContentVersion $parentVersion = null
    ): ContentVersion;

    /**
     * Create a branch version (work on "next" while current is live).
     */
    public function createBranch(
        Content $content,
        string $branchName,
        ?ContentVersion $fromVersion = null
    ): ContentVersion;

    /**
     * Merge a branch back to main.
     */
    public function mergeBranch(Content $content, string $branchName): ContentVersion;

    /**
     * Get the next version number for a content item.
     */
    private function nextVersionNumber(Content $content): int;
}
```

#### `App\Services\VersionDiffService`

Dedicated diff engine. Separated because diffing is complex enough to warrant its own class.

```php
namespace App\Services;

class VersionDiffService
{
    /**
     * Generate a structured diff between two versions.
     */
    public function diff(ContentVersion $from, ContentVersion $to): VersionDiff;

    /**
     * Generate a text-based diff for the body field.
     * Uses word-level diff for readable output.
     */
    public function bodyDiff(string $from, string $to): array;

    /**
     * Diff structured fields (JSON comparison).
     */
    public function structuredFieldsDiff(?array $from, ?array $to): array;

    /**
     * Diff SEO data.
     */
    public function seoDiff(?array $from, ?array $to): array;

    /**
     * Diff content blocks between two versions.
     */
    public function blocksDiff(ContentVersion $from, ContentVersion $to): array;
}
```

#### `App\DTOs\VersionDiff` (Value Object)

```php
namespace App\DTOs;

class VersionDiff
{
    public function __construct(
        public readonly string $fromVersionId,
        public readonly string $toVersionId,
        public readonly int $fromVersionNumber,
        public readonly int $toVersionNumber,
        public readonly array $titleDiff,       // [from, to, changed]
        public readonly array $excerptDiff,     // [from, to, changed]
        public readonly array $bodyDiff,        // word-level diff array
        public readonly array $seoDiff,         // field-by-field comparison
        public readonly array $structuredDiff,  // JSON diff
        public readonly array $blocksDiff,      // added/removed/modified blocks
        public readonly array $metadata,        // author, timestamp, scores
    ) {}

    public function hasChanges(): bool;
    public function toArray(): array;
    public function summary(): string;  // "Title changed, 23 words added, SEO description updated"
}
```

### 3.4 API Endpoints

#### Admin Routes (Inertia/Web) — `routes/web.php`

```
# Version management
GET    /admin/content/{id}/versions                  → VersionController@index
GET    /admin/content/{id}/versions/{versionId}      → VersionController@show
POST   /admin/content/{id}/versions/draft             → VersionController@saveDraft
POST   /admin/content/{id}/versions/{versionId}/promote → VersionController@promote
POST   /admin/content/{id}/versions/{versionId}/label → VersionController@updateLabel
POST   /admin/content/{id}/rollback/{versionId}       → VersionController@rollback

# Diff
GET    /admin/content/{id}/diff/{fromId}/{toId}       → VersionController@diff

# Scheduled publishing
POST   /admin/content/{id}/schedule                    → VersionController@schedule
DELETE /admin/content/{id}/schedule/{scheduleId}       → VersionController@cancelSchedule

# Branches
POST   /admin/content/{id}/branches                    → VersionController@createBranch
POST   /admin/content/{id}/branches/{branch}/merge     → VersionController@mergeBranch
```

#### Public API — `routes/api.php`

```
# Content delivery (read-only) — add version parameter
GET  /api/v1/content/{slug}?version={number}          → ContentController@show (extended)

# Authenticated management API
GET  /api/v1/content/{id}/versions                     → list versions
GET  /api/v1/content/{id}/versions/{versionId}         → single version detail
POST /api/v1/content/{id}/versions                     → create version
POST /api/v1/content/{id}/versions/{versionId}/rollback → rollback
GET  /api/v1/content/{id}/diff/{fromId}/{toId}         → diff
POST /api/v1/content/{id}/schedule                     → schedule publish
```

### 3.5 Controller: `VersionAdminController`

```php
namespace App\Http\Controllers\Admin;

class VersionAdminController extends Controller
{
    public function __construct(
        private VersioningService $versioning,
        private VersionDiffService $diffService,
    ) {}

    public function index(string $contentId)
    {
        $content = Content::with('versions')->findOrFail($contentId);
        // Return Inertia page with version timeline
        return Inertia::render('Content/Versions/Index', [...]);
    }

    public function saveDraft(Request $request, string $contentId)
    {
        $content = Content::findOrFail($contentId);
        $validated = $request->validate([
            'title' => 'required|string',
            'excerpt' => 'nullable|string',
            'body' => 'required|string',
            'structured_fields' => 'nullable|array',
            'seo_data' => 'nullable|array',
        ]);

        $draft = $this->versioning->saveDraft($content, $validated, $request->user());
        return back()->with('success', 'Draft saved.');
    }

    public function promote(string $contentId, string $versionId)
    {
        $version = ContentVersion::where('content_id', $contentId)->findOrFail($versionId);
        $this->versioning->promoteDraft($version);
        return back()->with('success', 'Version finalized.');
    }

    public function rollback(Request $request, string $contentId, string $versionId)
    {
        $content = Content::findOrFail($contentId);
        $targetVersion = ContentVersion::where('content_id', $contentId)->findOrFail($versionId);
        $this->versioning->rollback($content, $targetVersion, $request->user());
        return back()->with('success', 'Rolled back to version '.$targetVersion->version_number);
    }

    public function diff(string $contentId, string $fromId, string $toId)
    {
        $from = ContentVersion::where('content_id', $contentId)->findOrFail($fromId);
        $to = ContentVersion::where('content_id', $contentId)->findOrFail($toId);
        $diff = $this->diffService->diff($from, $to);

        return Inertia::render('Content/Versions/Diff', [
            'diff' => $diff->toArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function schedule(Request $request, string $contentId)
    {
        $validated = $request->validate([
            'version_id' => 'required|exists:content_versions,id',
            'publish_at' => 'required|date|after:now',
        ]);

        $content = Content::findOrFail($contentId);
        $version = ContentVersion::findOrFail($validated['version_id']);

        $schedule = $this->versioning->schedulePublish(
            $content, $version,
            Carbon::parse($validated['publish_at']),
            $request->user()
        );

        return back()->with('success', 'Publishing scheduled for '.$schedule->publish_at->format('M j, g:ia'));
    }
}
```

### 3.6 Jobs

#### `PublishScheduledVersion` Job

```php
namespace App\Jobs;

class PublishScheduledVersion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(VersioningService $versioning): void
    {
        $due = VersionSchedule::where('status', 'pending')
            ->where('publish_at', '<=', now())
            ->get();

        foreach ($due as $schedule) {
            $versioning->executeScheduledPublish($schedule);
        }
    }
}
```

Register in `routes/console.php`:
```php
Schedule::job(new PublishScheduledVersion)->everyMinute();
```

### 3.7 AI Pipeline Integration

The key integration point is in `PipelineExecutor` and the stage jobs. Each stage that modifies content creates a version with provenance.

#### Modified Flow

```
Brief → Stage 1 (ContentCreator)    → Version N   (parent: null,   stage: content_creator)
      → Stage 2 (SEO Optimizer)     → Version N+1 (parent: N,      stage: seo_optimizer)
      → Stage 3 (Editorial Review)  → Version N+2 (parent: N+1,    stage: editorial_review)
      → Stage 4 (Image Generation)  → no new version (asset only)
      → Stage 5 (Publish)           → Version N+2 promoted to current_version_id
```

#### Integration in `RunAgentStage` Job

After each AI stage produces content, instead of overwriting, call:

```php
$version = $this->versioning->createFromPipelineStage(
    run: $pipelineRun,
    stageName: $stage['name'],
    data: [
        'title' => $result['title'],
        'body' => $result['body'],
        'seo_data' => $result['seo_data'] ?? null,
        // ...
    ],
    parentVersion: $content->currentVersion,
);

// Update content pointer to latest pipeline version
$content->update(['current_version_id' => $version->id]);
```

The `createFromPipelineStage` method sets:
- `author_type` = `'ai_agent'`
- `author_id` = stage persona name
- `pipeline_run_id` = run ID
- `pipeline_stage` = stage name
- `parent_version_id` = previous version
- `ai_metadata` = merged stage result metadata
- `change_reason` = auto-generated (e.g., "SEO optimization by seo-specialist")

### 3.8 Admin UI Components (Vue 3 + Inertia.js)

#### New Pages

```
resources/js/Pages/Content/Versions/
├── Index.vue          # Version timeline/history page
├── Diff.vue           # Side-by-side diff view
└── Schedule.vue       # Schedule publish modal/page
```

#### Enhanced Existing Pages

```
resources/js/Pages/Content/
├── Show.vue           # Add: version selector, draft indicator, rollback button
└── Index.vue          # Add: draft badge, scheduled indicator
```

#### New Components

```
resources/js/Components/Versioning/
├── VersionTimeline.vue       # Vertical timeline of versions with labels
├── VersionCard.vue           # Single version entry (number, author, label, scores)
├── DiffViewer.vue            # Side-by-side or inline diff renderer
├── DiffBlock.vue             # Single diff block (added/removed/changed)
├── AutoSaveIndicator.vue     # "Saving..." / "Saved" / "Unsaved changes" indicator
├── SchedulePublishModal.vue  # Date/time picker for scheduled publishing
├── RollbackConfirmModal.vue  # Confirmation dialog for rollback
├── BranchSelector.vue        # Dropdown to switch between version branches
├── ProvenanceChain.vue       # Visual provenance: which AI stage changed what
└── VersionLabelEditor.vue    # Inline editable label for versions
```

#### Auto-Save Implementation

The `Show.vue` page (content editor) gets auto-save via a composable:

```typescript
// composables/useAutoSave.ts
export function useAutoSave(contentId: string, debounceMs = 2000) {
    const status = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
    const lastSaved = ref<Date | null>(null);

    const save = useDebounceFn(async (data: ContentData) => {
        status.value = 'saving';
        try {
            await router.post(`/admin/content/${contentId}/versions/draft`, data, {
                preserveState: true,
                preserveScroll: true,
            });
            status.value = 'saved';
            lastSaved.value = new Date();
        } catch {
            status.value = 'error';
        }
    }, debounceMs);

    return { status, lastSaved, save };
}
```

#### Diff Viewer Design

The diff viewer shows:
1. **Header bar** — version numbers, authors, timestamps, scores
2. **Toggle** — side-by-side vs inline mode
3. **Sections** — Title, Excerpt, Body, SEO, Structured Fields, Blocks
4. **Color coding** — green (added), red (removed), yellow (changed)
5. **AI provenance** — if versions are from pipeline stages, show the agent/persona responsible

### 3.9 Text Diffing Strategy

For the body diff (markdown content), use **word-level diffing** rather than line-level:

- Backend: Use `jfcherng/php-diff` package (mature, performant)
- Produces structured diff output (opcodes: insert, delete, replace, equal)
- Frontend renders with `<ins>` and `<del>` tags, styled with Tailwind

For JSON fields (structured_fields, seo_data), use recursive key-level comparison:
- Detect added, removed, and changed keys
- Show old value → new value for changes

For blocks, compare by block ID and type:
- Added blocks (in `to` but not `from`)
- Removed blocks (in `from` but not `to`)
- Modified blocks (same ID, different data)
- Reordered blocks (same IDs, different sort_order)

## 4. Data Flow Diagrams

### 4.1 Auto-Save Draft Flow

```
Editor types → debounce (2s) → POST /admin/content/{id}/versions/draft
                                    ↓
                              VersioningService::saveDraft()
                                    ↓
                              Upsert draft version (is_draft=true)
                                    ↓
                              Update content.draft_version_id
                                    ↓
                              Return success → UI shows "Saved ✓"
```

### 4.2 Publish Flow (with Schedule)

```
User clicks "Schedule Publish"
    ↓
SchedulePublishModal → POST /admin/content/{id}/schedule
    ↓
Create VersionSchedule (status=pending, publish_at=datetime)
    ↓
Content.status = 'scheduled'
    ↓
[Every minute] PublishScheduledVersion job runs
    ↓
Find due schedules → executeScheduledPublish()
    ↓
Content.current_version_id = version.id
Content.status = 'published'
Content.published_at = now()
VersionSchedule.status = 'published'
```

### 4.3 Rollback Flow

```
User clicks "Rollback to v3" → Confirmation modal
    ↓
POST /admin/content/{id}/rollback/{versionId}
    ↓
VersioningService::rollback()
    ↓
Create NEW version (copy of v3's data)
    version_number = next in sequence
    parent_version_id = v3.id
    change_reason = "Rollback to version 3"
    author_type = 'human'
    ↓
Content.current_version_id = new version's ID
    ↓
Return → UI shows "Rolled back to v3"
```

**Design decision:** Rollback is **additive** — it creates a new version that copies the target version's content. This preserves complete history and makes rollback itself reversible.

### 4.4 AI Pipeline Provenance Flow

```
Pipeline starts → Brief
    ↓
Stage: content_creator
    → Creates Version N (author: content-creator persona)
       parent_version_id: existing current_version (if update)
       pipeline_stage: 'content_creator'
       ai_metadata: { model, tokens, cost, prompt_summary }
    ↓
Stage: seo_optimizer
    → Creates Version N+1 (author: seo-specialist persona)
       parent_version_id: Version N
       pipeline_stage: 'seo_optimizer'
       ai_metadata: { model, tokens, cost, changes_summary }
    ↓
Stage: editorial_review
    → Creates Version N+2 (author: editor-in-chief persona)
       parent_version_id: Version N+1
       pipeline_stage: 'editorial_review'
       ai_metadata: { model, tokens, cost, review_notes }
    ↓
Publish stage
    → content.current_version_id = Version N+2
    → content.status = 'published'
```

## 5. Package Dependencies

| Package | Purpose | Why |
|---------|---------|-----|
| `jfcherng/php-diff` | Text diffing | Mature, supports word-level diff, structured output |

No other new dependencies. The rest is built with Laravel primitives.

## 6. Migration Execution Order

1. `2026_03_07_000001_add_versioning_fields_to_content_versions_table.php`
2. `2026_03_07_000002_add_draft_version_id_to_contents_table.php`
3. `2026_03_07_000003_create_version_schedules_table.php`

## 7. File Structure (New/Modified)

```
app/
├── DTOs/
│   └── VersionDiff.php                          # NEW — diff value object
├── Http/Controllers/Admin/
│   └── VersionAdminController.php               # NEW — version management
├── Http/Controllers/Api/
│   └── VersionController.php                    # NEW — API version endpoints
├── Jobs/
│   └── PublishScheduledVersion.php              # NEW — scheduled publish job
├── Models/
│   ├── Content.php                              # MODIFIED — add draftVersion(), rollbackTo()
│   ├── ContentVersion.php                       # MODIFIED — add scopes, parentVersion()
│   └── VersionSchedule.php                      # NEW — scheduled publish model
├── Services/
│   ├── VersioningService.php                    # NEW — core versioning logic
│   └── VersionDiffService.php                   # NEW — diff engine
├── Pipelines/
│   └── PipelineExecutor.php                     # MODIFIED — create versions per stage
database/migrations/
│   ├── 2026_03_07_000001_add_versioning_fields_to_content_versions_table.php
│   ├── 2026_03_07_000002_add_draft_version_id_to_contents_table.php
│   └── 2026_03_07_000003_create_version_schedules_table.php
resources/js/
├── Components/Versioning/
│   ├── VersionTimeline.vue                      # NEW
│   ├── VersionCard.vue                          # NEW
│   ├── DiffViewer.vue                           # NEW
│   ├── DiffBlock.vue                            # NEW
│   ├── AutoSaveIndicator.vue                    # NEW
│   ├── SchedulePublishModal.vue                 # NEW
│   ├── RollbackConfirmModal.vue                 # NEW
│   ├── BranchSelector.vue                       # NEW
│   ├── ProvenanceChain.vue                      # NEW
│   └── VersionLabelEditor.vue                   # NEW
├── Composables/
│   └── useAutoSave.ts                           # NEW
├── Pages/Content/
│   ├── Show.vue                                 # MODIFIED — add version features
│   ├── Index.vue                                # MODIFIED — add draft/scheduled badges
│   └── Versions/
│       ├── Index.vue                            # NEW — version history page
│       ├── Diff.vue                             # NEW — diff view page
│       └── Schedule.vue                         # NEW — schedule publish page
routes/
├── api.php                                      # MODIFIED — add version endpoints
├── web.php                                      # MODIFIED — add admin version routes
└── console.php                                  # MODIFIED — add scheduled job
tests/
├── Feature/
│   ├── VersioningServiceTest.php                # NEW
│   ├── VersionDiffServiceTest.php               # NEW
│   ├── VersionAdminControllerTest.php           # NEW
│   ├── PublishScheduledVersionTest.php          # NEW
│   └── PipelineVersioningTest.php               # NEW
```

## 8. Trade-offs & Decisions

### 8.1 Additive Rollback (ADR)
**Decision:** Rollback creates a new version copying the target's data, rather than moving the pointer.
**Rationale:** Full history preservation. A rollback is itself a versioned event. You can "undo" a rollback.
**Trade-off:** More storage, but content text is small. Worth it.

### 8.2 Draft as Version Flag (not separate table)
**Decision:** Drafts are stored as `ContentVersion` records with `is_draft=true`.
**Rationale:** Unified model — drafts and published versions share the same schema. Simpler queries, no JOIN overhead. Draft auto-save upserts a single draft record per content item.
**Trade-off:** Draft versions consume version_number space. Mitigated by not assigning a version_number until promotion.

### 8.3 Separate VersionSchedule Table
**Decision:** Scheduled publishes get their own table rather than just a `scheduled_publish_at` column.
**Rationale:** Supports cancel/history, multiple schedules (cancel old, create new), audit trail of who scheduled what. The job needs an efficient index on `(status, publish_at)`.
**Trade-off:** Extra table, but clean separation of concerns.

### 8.4 Branch as String Field (not separate table)
**Decision:** Version branches are a string field on `content_versions` (default: 'main').
**Rationale:** Simple. Most content won't use branches. No extra table needed for a v1. Can always add a `version_branches` table later if we need branch metadata.
**Trade-off:** No branch-level metadata (description, created_by). Acceptable for v1.

### 8.5 Word-Level Diff (not line-level)
**Decision:** Use word-level diff for body content.
**Rationale:** Content is prose, not code. Line-level diff is noisy for paragraphs. Word-level shows exactly what changed.
**Trade-off:** Slightly slower for very long documents. Acceptable — content articles are typically < 10K words.

## 9. Quality Gates

- [ ] PHPStan Level 5 — all new code passes
- [ ] Pint — code style conformance
- [ ] Feature tests — VersioningService, DiffService, Controller, Job, Pipeline integration
- [ ] No modifications to existing migrations
- [ ] API documentation updated (OpenAPI spec)

## 10. Implementation Order (Suggested)

1. **Phase 1: Schema & Models** — Migrations, model updates, VersionSchedule model
2. **Phase 2: Service Layer** — VersioningService, VersionDiffService, VersionDiff DTO
3. **Phase 3: API & Controllers** — VersionAdminController, API routes
4. **Phase 4: Pipeline Integration** — Modify PipelineExecutor and stage jobs
5. **Phase 5: Admin UI** — Vue components, auto-save composable, diff viewer
6. **Phase 6: Scheduled Publishing** — Job, scheduler registration, cancel flow
7. **Phase 7: Tests** — Feature tests for all service methods and endpoints

---

*This architecture extends the existing ContentVersion foundation with minimal schema changes (3 migrations), a clean service layer, and a modular UI. The design prioritizes history preservation (additive rollback), AI provenance (stage-level tracking), and developer ergonomics (unified draft/version model).*
