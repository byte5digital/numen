# Content Versioning & Draft System

> **Feature branch:** `feature/versioning`
> **Status:** Implementation Complete
> **Numen version:** 0.2.0+
> **Author:** Scribe 📝 (numen-docs)
> **Last updated:** 2026-03-07

---

## Table of Contents

1. [Overview](#1-overview)
2. [User Guide](#2-user-guide)
3. [Admin Guide](#3-admin-guide)
4. [Developer Guide](#4-developer-guide)
5. [Migration Guide](#5-migration-guide)

> **API Reference:** For complete endpoint documentation, see [`docs/api/versioning-api.md`](../api/versioning-api.md).

---

## 1. Overview

Numen's Content Versioning & Draft System gives you a complete audit trail for every content item — plus the tools to draft, review, schedule, and roll back changes with confidence.

### What It Does

| Capability | Description |
|---|---|
| **Version history** | Every publish creates an immutable snapshot with a number, author, and timestamp |
| **Named versions** | Label important snapshots — "v1.0 Launch Copy", "SEO Refresh Q2" |
| **Auto-save drafts** | Changes are buffered per-user without polluting version history |
| **Two-step rollback** | Restore any historical version as a draft for review, then publish explicitly |
| **Side-by-side diff** | Compare any two versions with word-level and line-level highlighting |
| **Scheduled publishing** | Set a future date/time; the system publishes automatically |
| **Version branching** | Work on next version while current version stays live |
| **AI provenance** | Pipeline-generated versions carry full token/model/cost metadata |
| **Soft-locking** | Prevents concurrent edits — editor holds a lock while working |
| **Space isolation** | Authorization is space-scoped; editors only see their own space's content |

### Core Concepts

```
Content
  ├── current_version_id  → live/published version (what visitors see)
  ├── draft_version_id    → active in-progress draft
  └── autosaveDraft       → per-user ephemeral buffer (not a version)

ContentVersion
  ├── status: draft | published | archived | scheduled
  ├── label: optional human-readable name ("v1.0 Launch Copy")
  ├── parent_version_id: what version this was based on / branched from
  └── content_hash: SHA-256 for fast duplicate detection

ContentDraft (auto-save buffer)
  └── one per user per content item — discarded on publish

ScheduledPublish
  └── queued job + cron safety-net for future publishing
```

**Key principle:** Versions are immutable snapshots. Drafts are mutable workspaces. Publishing is a pointer swap.

### Status Lifecycle

```
draft ──publish──► published ──(next publish)──► archived
draft ──schedule──► scheduled ──(publish_at)──► published
rollback ──► new draft ──review──► publish
```

### Rollback is Two-Step

> ⚠️ **Important:** Rollback creates a **draft** — it does NOT auto-publish. You review the restored content, then explicitly click **Publish** when you're ready. This is a deliberate safety mechanism.

---

## 2. User Guide

This section explains how to use the versioning system as an editor in the Numen admin UI.

### 2.1 The Version Sidebar

Every content item's editor has a **Version Sidebar** on the right-hand side. It shows a chronological timeline of all versions.

**Each version entry shows:**

| Element | Description |
|---|---|
| Version number | `v1`, `v2`, `v3` — monotonically increasing |
| Label | Optional name, e.g. "SEO Refresh" (click ✏️ to edit inline) |
| Status badge | 🟢 Published · 🟡 Draft · 🔵 Scheduled · ⚫ Archived |
| Author | Your name, or 🤖 for AI-generated versions |
| Timestamp | Relative ("3 days ago") — hover for exact UTC time |
| Actions | **Publish**, **Rollback**, **Branch**, **Diff** |

Click any version row to **preview** its content in a read-only panel without affecting the live version.

---

### 2.2 Auto-Save

The editor **automatically saves your work** every 2 seconds while you type. This is stored in your personal auto-save buffer — it does **not** create a version or affect what visitors see.

**Auto-save indicators:**

| Status | Meaning |
|---|---|
| `Saving…` | A save is in progress |
| `Saved ✓` | Your changes are safely stored |
| `Unsaved changes` | You've typed since the last save |
| `Error — click to retry` | Save failed (check your connection) |

**Recovering unsaved work:** If you close the browser mid-edit, a recovery banner appears on your next visit:
> *"You have unsaved changes from March 7 at 11:04. Recover or discard?"*

Click **Recover** to restore from your auto-save, or **Discard** to start fresh from the last published version.

> Auto-save data is **personal** — only you can see your own auto-save buffer. Other editors working on the same content item have their own independent buffers.

---

### 2.3 Creating a Draft

When you want to start editing a published piece:

1. Open the content item in the editor
2. Click **"New Draft"** in the toolbar
3. A new draft version is created, branched from the current live version
4. The status indicator in the toolbar shows **`DRAFT v4`**
5. Start editing — auto-save kicks in immediately

If the content already has a draft in progress (possibly from another session or another editor), you'll see a warning:
> *"A draft already exists (v4, by Jane, 10 minutes ago). Open it or create a new branch?"*

---

### 2.4 Saving a Named Version (Save Point)

Auto-save protects your work. A **named version** is a deliberate snapshot — a milestone you want to be able to reference or roll back to.

1. When your draft is in good shape, click **"Save Version"**
2. Enter a label: `"v1.0 Launch Copy"`, `"SEO Refresh Q2"`, `"Post-edit review"`
3. Click **Save** — the version is locked in with your label and a content hash

Named versions appear in the Version Sidebar with their label displayed alongside the version number. Unlabeled versions show as `v3 (draft)` or `v3 (archived)`.

> **Tip:** Label before you publish. It's much easier to find `"SEO Refresh"` in a 20-version timeline than `v7`.

---

### 2.5 Publishing

Publishing makes a version live — it becomes what visitors see via the API.

1. In the Version Sidebar, click **Publish** next to the version you want to make live
2. A confirmation dialog appears showing the version number and label (if set)
3. Confirm — the version goes live immediately

**What happens on publish:**
- The previous `published` version moves to `archived`
- This version becomes `published`
- `content.published_at` is updated to now
- A `ContentPublished` event fires (cache invalidation, webhooks, etc.)
- If this was your active draft, the draft pointer is cleared

---

### 2.6 Scheduling a Future Publish

Need to go live at a specific date and time? Use **Scheduled Publishing**.

1. In the Version Sidebar, click **"Schedule"** next to the version
2. The **Schedule Publish** modal opens
3. Pick a date and time using the date/time picker
   - The modal shows both your local timezone and the UTC equivalent
   - Add optional notes (visible in the admin audit trail)
4. Click **"Confirm Schedule"**
5. The version status changes to **`scheduled`** with a clock icon 🕑

**To cancel a scheduled publish:**
- Click the clock icon 🕑 next to the scheduled version
- Confirm cancellation in the dialog
- The version reverts to `draft` status

> There can only be one pending schedule per content item. Scheduling a new version automatically cancels any existing pending schedule.

---

### 2.7 Comparing Versions (Diff View)

Not sure what changed between two versions? Use the **Diff Viewer**.

1. In the Version Sidebar, click **"Diff"** on any version, **or** select two versions and click **"Compare"**
2. The **Version Diff** panel opens with:

| Section | What you see |
|---|---|
| **Header** | Version numbers, authors, timestamps, quality/SEO scores |
| **Summary bar** | e.g. "title changed, body: +5/-2 lines, 2 blocks added" |
| **View toggle** | Switch between side-by-side and unified (inline) diff |
| **Title / Excerpt** | Word-level diff — added words highlighted green, removed words in red |
| **Body** | Line-level diff with 3-line context around each change |
| **Blocks** | Visual cards showing added 🟢, removed 🔴, and modified 🟡 blocks |
| **SEO panel** | Field-by-field comparison of changed metadata |

---

### 2.8 Rolling Back to a Previous Version

Made a mistake? Published something that needs to be reverted? Rollback restores a historical version — safely, as a two-step process.

**Step 1 — Trigger Rollback:**
1. In the Version Sidebar, click **"Rollback"** next to the version you want to restore
2. A confirmation dialog shows: *"This will create a new draft with the content of v2 (Original Launch Copy). You'll need to publish it manually."*
3. Confirm — a new draft is created with all the content from the target version
4. The draft appears at the top of the Version Sidebar: `v5 (draft) — Rollback to v2`

**Step 2 — Review and Publish:**
5. Review the restored content in the editor
6. Make any additional tweaks if needed
7. Click **Publish** to make it live

> **Why two steps?** Rollbacks go live only after you explicitly review and publish. This prevents accidental rollbacks from immediately affecting live content. You can always compare (`Diff`) the rollback draft against the current live version before committing.

> **History is never lost.** The rollback creates a new version — it doesn't delete any history. You can "undo" a rollback by rolling back again to the version you came from.

---

### 2.9 Version Branches

Branches let you **work on the next version while the current one stays live**.

**Example use case:** You have a published blog post (`v3`). A redesign is coming in two weeks. You want to start writing the new version now, without touching the live content.

1. In the Version Sidebar, click **"Branch"** next to the live version (`v3`)
2. Give the branch a label: `"v4.0 Redesign"`
3. A new draft is created from `v3` and set as your working draft
4. Visitors still see `v3` while you work in the branch
5. When ready, publish the branch version — `v3` archives, `v4` goes live

You can have multiple draft branches of the same content item active at once (one per editor, or multiple exploratory branches).

---

### 2.10 AI-Generated Versions

When the AI pipeline runs and generates content, it creates a version automatically. These versions appear in your history with:

- A 🤖 robot icon instead of a user avatar
- A link to the **Pipeline Run** (click to see which agents ran, models used, tokens consumed)
- Quality score and SEO score from the editorial and SEO stages
- A `change_reason` like *"Pipeline run: Blog Content Pipeline"*

**AI versions always start as `draft`** — they require human review and an explicit publish. This is a safety gate: AI output goes live only after a human approves it.

Unless `NUMEN_PIPELINE_AUTO_PUBLISH=true` is set in your environment, which bypasses the human review step.

---

## 3. Admin Guide

### 3.1 Managing Scheduled Publishes

View all scheduled publishes across your space from the **Admin → Scheduled** panel. Each row shows:
- Content item name
- Version number and label
- Scheduled publish time (local + UTC)
- Who scheduled it
- Status: `pending` | `published` | `cancelled` | `failed`

**Failed schedules:** If the queue job fails 3 times (with 60-second backoff between retries), the status is set to `failed`. The cron safety net (`numen:publish-scheduled`) will catch and retry it on the next minute tick.

To manually trigger a failed or missed scheduled publish:
```bash
php artisan numen:publish-scheduled
```

---

### 3.2 Resolving Edit Locks (Soft Locking)

Numen uses **soft locking** to prevent two editors from overwriting each other's work. When an editor opens a draft for editing, the system records `locked_by` (user ID) and `locked_at` (timestamp) on the `ContentVersion` record.

If another editor tries to open the same draft, they receive a **`423 Locked`** response with:
```json
{
  "message": "This version is currently being edited by Jane (since 5 minutes ago).",
  "locked_by": "01hwzy...",
  "locked_at": "2026-03-07T11:00:00Z"
}
```

**Locks are automatically released** when the editor navigates away (browser `beforeunload` event calls the lock release endpoint).

**To manually force-release a stale lock** (admin only):
```bash
# Via Artisan tinker
ContentVersion::find('version-id')->update(['locked_by' => null, 'locked_at' => null]);
```

Or through the admin UI: Admin → Content → Edit → *"Force unlock"* (visible to admins only).

Lock staleness threshold: locks older than **15 minutes** are considered stale and can be force-acquired by any editor.

---

### 3.3 Monitoring AI Pipeline Versions

Track AI-generated content from **Admin → Pipeline Runs**:

- Each completed run appears with: pipeline name, content item, versions created, models used, total cost
- Click a run to see the **provenance chain**: which stage created which version, with per-stage token/cost breakdown
- Filter by date range, pipeline name, or content item

**AI metadata stored per version** (`ai_metadata` JSON field):
```json
{
  "pipeline_id": "01hwzy...",
  "pipeline_run_id": "01hwzz...",
  "stages_completed": ["content_creator", "seo_optimizer", "editorial_director"],
  "models_used": ["claude-sonnet-4-6", "claude-haiku-4-5-20251001", "claude-opus-4-6"],
  "total_tokens": 4820,
  "total_cost_usd": 0.0214,
  "brief_id": "01hwzw...",
  "generated_at": "2026-03-07T10:45:00Z"
}
```

---

### 3.4 Rate Limits

The versioning API enforces tiered rate limits to protect server resources:

| Endpoint group | Limit |
|---|---|
| Read endpoints (list, show, diff) | 60 requests/minute per user |
| Write/publish endpoints | 30 requests/minute per user |
| Auto-save | 30 saves/minute per user |

Rate limit headers are returned on every response:
```
X-RateLimit-Limit: 30
X-RateLimit-Remaining: 28
```

A `429 Too Many Requests` response is returned when limits are exceeded. The `Retry-After` header indicates when the window resets.

---

### 3.5 Authorization & Space Isolation

Authorization is managed by `App\Policies\ContentPolicy`.

| Ability | Required role | Space-scoped? |
|---|---|---|
| `view` | `editor` | ✓ — editors only see their space |
| `modify` | `editor` | ✓ |
| `publish` | `editor` | ✓ |
| `rollback` | `editor` | ✓ |
| `schedule` | `editor` | ✓ |
| `cancelSchedule` | `editor` | ✓ |
| All abilities | `admin` | ✗ — admins bypass all checks |

**Space scoping** works as follows: if a user has `space_id` set, they can only access content where `content.space_id` matches. Users with `space_id = null` can access content across all spaces (super-editors / admins).

Cross-space access attempts return `403 Forbidden`.

---

## 4. Developer Guide

### 4.1 VersioningService

**Namespace:** `App\Services\Versioning\VersioningService`

The central service for all versioning operations. Inject via constructor:

```php
use App\Services\Versioning\VersioningService;

class MyController extends Controller
{
    public function __construct(private VersioningService $versioning) {}
}
```

Or resolve from the container:
```php
$versioning = app(VersioningService::class);
```

---

#### `createDraft(Content $content, ?ContentVersion $branchFrom = null): ContentVersion`

Creates a new draft version branched from `$branchFrom` (defaults to `$content->currentVersion`). Copies all content fields and clones all content blocks. Sets `content.draft_version_id`.

```php
// Branch from current live version
$draft = $versioning->createDraft($content);

// Branch from a specific historical version
$draft = $versioning->createDraft($content, $historicalVersion);
```

**Behaviour:**
- Next `version_number` is assigned automatically
- Content fields (`title`, `body`, `structured_fields`, etc.) are copied verbatim
- Content blocks are cloned in order
- `parent_version_id` points to the branched-from version
- `change_reason` is auto-set: `"Branched from v{N}"` (or `null` for main draft)

---

#### `autoSave(Content $content, User $user, array $data): ContentDraft`

Upserts the auto-save buffer for a given user/content pair. One `ContentDraft` record per `(content_id, user_id)` pair. Increments `save_count` atomically. **Does not create a `ContentVersion`.**

```php
$draft = $versioning->autoSave($content, $request->user(), [
    'title' => 'New title',
    'body'  => 'Updated body content...',
    'body_format' => 'markdown',
]);

// $draft->save_count  → increments each call
// $draft->last_saved_at → updated to now()
```

---

#### `saveVersion(ContentVersion $draft, string $label, ?string $changeReason = null): ContentVersion`

Promotes a draft to a named save point: sets the `label`, computes and stores `content_hash`, and clears the **current user's** auto-save buffer (not other users').

```php
$namedVersion = $versioning->saveVersion(
    $draft,
    'v2.0 SEO Refresh',
    'Annual SEO audit — updated meta and structured data'
);
```

---

#### `publish(Content $content, ContentVersion $version): void`

Makes a version live. Archives the previous published version.

```php
$versioning->publish($content, $draftVersion);
```

**Side effects (in order):**
1. All currently `published` versions for this content → `archived`
2. `$version->status` → `published`
3. `content.current_version_id` → `$version->id`
4. `content.status` → `published`
5. `content.published_at` → `now()`
6. If `content.draft_version_id === $version->id`, clears the draft pointer
7. Fires `ContentPublished` event

---

#### `schedule(Content $content, ContentVersion $version, Carbon $publishAt, ?string $notes = null): ScheduledPublish`

Schedules a version for future publishing. Cancels any existing pending schedule for the content.

```php
use Carbon\Carbon;

$schedule = $versioning->schedule(
    $content,
    $version,
    Carbon::parse('2026-03-15 09:00:00'),
    'Q1 campaign launch'
);
// Returns App\Models\ScheduledPublish
```

**Side effects:**
- Existing pending `ScheduledPublish` records for this content → `cancelled`
- `$version->status` → `scheduled`; `$version->scheduled_at` → `$publishAt`
- `content.status` → `scheduled`; `content.scheduled_publish_at` → `$publishAt`
- Creates new `ScheduledPublish` record with `status = pending`
- Dispatches `PublishScheduledContent` job with `->delay($publishAt)`

---

#### `rollback(Content $content, ContentVersion $targetVersion): ContentVersion`

Creates a new **draft** version copying all content from `$targetVersion`. Blocks are cloned. Sets `content.draft_version_id` to the new draft. **Does NOT auto-publish** — the caller must explicitly call `publish()` after review.

```php
$rollbackDraft = $versioning->rollback($content, $oldVersion);

// At this point: $rollbackDraft->status === 'draft'
// The live version is still the previous published version
// Editor reviews, then:
$versioning->publish($content, $rollbackDraft);
```

**Why two steps?** This is deliberate: a rollback that auto-published could instantly break production if triggered by mistake. Human review is required.

---

#### `diff(ContentVersion $versionA, ContentVersion $versionB): VersionDiff`

Compares two versions. Returns a `VersionDiff` value object.

```php
$diff = $versioning->diff($versionA, $versionB);

echo $diff->hasChanges();  // true
echo $diff->summary();     // "title changed, body: +5/-2 lines, 1 SEO fields changed"

// JSON-serializable for API responses
return response()->json(['data' => $diff]);
```

---

#### `branch(Content $content, ContentVersion $fromVersion, ?string $label = null): ContentVersion`

Creates a draft branched from any version. Syntactic sugar over `createDraft()` with an immediate label.

```php
$branch = $versioning->branch($content, $liveVersion, 'v3.0 Redesign');
// $liveVersion is still live; $branch is a new draft
```

---

### 4.2 DiffEngine & VersionDiff

**Namespace:** `App\Services\Versioning\DiffEngine`, `App\Services\Versioning\VersionDiff`

The `DiffEngine` is injected into `VersioningService`. It uses `jfcherng/php-diff` for line/word-level diffing.

#### Diff strategies by field

| Field | Strategy | Notes |
|---|---|---|
| `title`, `excerpt` | Word-level diff | Split on word boundaries |
| `body` | Line-level diff with 3-line context | Suited for markdown prose |
| `seo_data` | Key-by-key comparison | Old/new values per key |
| `blocks` | Sort-order keyed comparison | Added/removed/modified |

#### VersionDiff properties

```php
$diff->versionA;     // ContentVersion — "before"
$diff->versionB;     // ContentVersion — "after"
$diff->fieldDiffs;   // array — diffs for title, excerpt, body
$diff->blockDiffs;   // array — added/removed/modified blocks
$diff->seoDiffs;     // array — changed SEO keys

$diff->hasChanges(); // bool
$diff->summary();    // string — human-readable summary
$diff->jsonSerialize(); // array — use in JSON responses directly
```

#### Example: rendering diff in a controller

```php
public function diff(Content $content, Request $request): JsonResponse
{
    $this->authorize('view', $content);

    $request->validate([
        'version_a' => 'required|exists:content_versions,id',
        'version_b' => 'required|exists:content_versions,id',
    ]);

    $a = ContentVersion::with('blocks')->findOrFail($request->input('version_a'));
    $b = ContentVersion::with('blocks')->findOrFail($request->input('version_b'));

    // Ensure both belong to this content
    abort_unless(
        $a->content_id === $content->id && $b->content_id === $content->id,
        422, 'Versions must belong to this content item.'
    );

    return response()->json(['data' => $this->versioning->diff($a, $b)]);
}
```

---

### 4.3 ContentVersion Model

**Namespace:** `App\Models\ContentVersion`

#### Key fields

| Field | Type | Description |
|---|---|---|
| `id` | ULID | Primary key |
| `content_id` | ULID | Parent content item |
| `version_number` | int | Monotonically increasing per content item |
| `label` | string\|null | Human-readable name |
| `status` | string | `draft` \| `published` \| `archived` \| `scheduled` |
| `parent_version_id` | ULID\|null | Branch parent — forms provenance chain |
| `author_type` | string | `human` \| `ai_agent` |
| `author_id` | string | User ULID (human) or Pipeline ULID (AI) |
| `change_reason` | string\|null | Why this version was created |
| `pipeline_run_id` | ULID\|null | AI provenance link |
| `ai_metadata` | array\|null | Models, tokens, cost for AI versions |
| `quality_score` | decimal(2)\|null | Editorial quality score (0–1) |
| `seo_score` | decimal(2)\|null | SEO quality score (0–1) |
| `scheduled_at` | datetime\|null | When to publish (for scheduled versions) |
| `content_hash` | string(64)\|null | SHA-256 of content fields for equality checks |
| `locked_by` | ULID\|null | User currently editing this version |
| `locked_at` | datetime\|null | When the edit lock was acquired |

#### Scopes

```php
ContentVersion::published()  // WHERE status = 'published'
ContentVersion::drafts()     // WHERE status = 'draft'
ContentVersion::scheduled()  // WHERE status = 'scheduled'
ContentVersion::labeled()    // WHERE label IS NOT NULL
```

#### Helper methods

```php
$version->isPublished();     // bool
$version->isDraft();         // bool
$version->isScheduled();     // bool
$version->isAiGenerated();   // bool — author_type === 'ai_agent'
$version->hasBlocks();       // bool — checks for related ContentBlock records
$version->computeHash();     // string — SHA-256 of title/excerpt/body/structured_fields/seo_data
```

#### Relations

```php
$version->content;       // BelongsTo Content
$version->blocks;        // HasMany ContentBlock (ordered by sort_order)
$version->pipelineRun;   // BelongsTo PipelineRun
$version->parentVersion; // BelongsTo ContentVersion — what this was branched from
$version->childVersions; // HasMany ContentVersion — versions branched from this one
```

---

### 4.4 ContentDraft Model

**Namespace:** `App\Models\ContentDraft`

The auto-save buffer. One record per `(content_id, user_id)` — unique constraint enforced at the DB level. Use `VersioningService::autoSave()` rather than creating directly.

```php
// Retrieve a user's auto-save buffer
$draft = ContentDraft::where('content_id', $contentId)
    ->where('user_id', $userId)
    ->first();

// $draft is null if no auto-save exists
```

#### Relations
```php
$draft->content;      // BelongsTo Content
$draft->user;         // BelongsTo User
$draft->baseVersion;  // BelongsTo ContentVersion (the version being edited from)
```

---

### 4.5 ScheduledPublish Model

**Namespace:** `App\Models\ScheduledPublish`

Tracks scheduled publish jobs. Do not create directly — use `VersioningService::schedule()`.

#### Scopes

```php
ScheduledPublish::pending()  // WHERE status = 'pending'
ScheduledPublish::due()      // pending + WHERE publish_at <= now()
```

#### Relations
```php
$schedule->content;    // BelongsTo Content
$schedule->version;    // BelongsTo ContentVersion
$schedule->scheduler;  // BelongsTo User (via scheduled_by FK)
```

#### Statuses

| Status | Meaning |
|---|---|
| `pending` | Job is queued, waiting to fire |
| `published` | Job ran successfully |
| `cancelled` | Cancelled by user (or superseded by a new schedule) |
| `failed` | Job failed all 3 retry attempts |

---

### 4.6 Pipeline Integration

Every completed AI pipeline run creates a `ContentVersion` via `PipelineVersioningIntegration::onPipelineComplete()`.

**Integration class:** `App\Services\Versioning\PipelineVersioningIntegration`

```php
use App\Services\Versioning\PipelineVersioningIntegration;

// In your PipelineExecutor or stage job, after content generation:
$integration = app(PipelineVersioningIntegration::class);
$version = $integration->onPipelineComplete($pipelineRun, $generatedContent);

// $version->status === 'draft'  (always — AI output requires human review)
// $version->author_type === 'ai_agent'
// $version->pipeline_run_id === $pipelineRun->id
// $version->ai_metadata contains models, tokens, cost
```

**Generated `ai_metadata` structure:**
```json
{
  "pipeline_id": "01hwzy...",
  "pipeline_run_id": "01hwzz...",
  "stages_completed": ["content_creator", "seo_optimizer", "editorial_director"],
  "models_used": ["claude-sonnet-4-6", "claude-haiku-4-5-20251001"],
  "total_tokens": 4820,
  "total_cost_usd": 0.0214,
  "brief_id": "01hwzw...",
  "generated_at": "2026-03-07T10:45:00Z"
}
```

**Manual pipeline version creation (custom stages):**
```php
$version = $content->versions()->create([
    'version_number'   => $content->versions()->max('version_number') + 1,
    'title'            => $generatedContent['title'],
    'body'             => $generatedContent['body'],
    'body_format'      => 'markdown',
    'author_type'      => 'ai_agent',
    'author_id'        => (string) $pipelineRun->pipeline_id,
    'change_reason'    => "Pipeline run: {$pipelineRun->pipeline->name}",
    'pipeline_run_id'  => $pipelineRun->id,
    'ai_metadata'      => [ /* see above */ ],
    'status'           => 'draft',   // always draft — human must publish
    'parent_version_id' => $content->current_version_id,
]);

// Compute and store the content hash after attaching blocks
$version->update(['content_hash' => $version->computeHash()]);
```

---

### 4.7 Events

Subscribe to versioning events for cache invalidation, webhooks, or notifications:

| Event | Class | When it fires |
|---|---|---|
| Version published | `App\Events\Content\ContentPublished` | Any publish (manual or scheduled) |
| Content scheduled | `App\Events\Content\ContentScheduled` | When a version is scheduled |
| Content rolled back | `App\Events\Content\ContentRolledBack` | When a rollback draft is created |
| Draft auto-saved | `App\Events\Content\ContentDraftAutoSaved` | Each auto-save upsert |
| Version created | `App\Events\Content\ContentVersionCreated` | Any new version (human or AI) |

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    ContentPublished::class => [
        InvalidateContentCache::class,
        SendPublishWebhook::class,
    ],
];
```

Or using the attribute-based listener (Laravel 11+):
```php
use Illuminate\Events\Attributes\AsEventListener;

#[AsEventListener]
class InvalidateContentCache
{
    public function handle(ContentPublished $event): void
    {
        Cache::tags(['content', $event->content->slug])->flush();
    }
}
```

---

### 4.8 Scheduled Publishing — How It Works

Two mechanisms fire in parallel (both are idempotent — whichever runs first wins):

**1. Delayed Queue Job** *(primary — fires at exact scheduled time)*

```php
// Dispatched by VersioningService::schedule()
PublishScheduledContent::dispatch($schedule->id)->delay($publishAt);
```

- Retries: 3 attempts with 60-second backoff
- Before publishing, checks `status === 'pending'`; no-ops if already cancelled or published
- On failure after all retries: sets `status = 'failed'` and rethrows

**2. Artisan Cron** *(safety net — runs every minute)*

```bash
php artisan numen:publish-scheduled
```

Registered in `routes/console.php`:
```php
Schedule::command('numen:publish-scheduled')->everyMinute()->withoutOverlapping();
```

The cron finds all `ScheduledPublish` records where `status = pending AND publish_at <= now()` and processes them. This catches any records that the queue job missed (e.g., during worker downtime).

**Ensure your queue worker and scheduler are running:**
```bash
# Queue worker
php artisan queue:work --queue=default --tries=3

# Scheduler (add to crontab)
* * * * * cd /path/to/numen && php artisan schedule:run >> /dev/null 2>&1
```

---

### 4.9 Soft Locking

Edit locks prevent concurrent edits from overwriting each other.

**Lock fields on `content_versions`:**
- `locked_by` (ULID) — user currently editing
- `locked_at` (datetime) — when the lock was acquired

**Lock acquisition** (HTTP 423 if already locked by another user):
```
POST /api/v1/content/{content}/versions/{version}/lock
```

**Lock release** (called on `beforeunload` in the Vue editor):
```
DELETE /api/v1/content/{content}/versions/{version}/lock
```

**Stale lock threshold:** 15 minutes. Locks older than 15 minutes can be force-acquired by any editor. Admins can force-release at any time.

**Checking lock status in PHP:**
```php
$isLocked = $version->locked_by !== null
    && $version->locked_at?->isAfter(now()->subMinutes(15));

$lockedByCurrentUser = $version->locked_by === (string) Auth::id();
```

---

## 5. Migration Guide

### 5.1 Overview

The versioning system adds **4 new migrations** — all additive. No existing tables are dropped or modified. All new columns are nullable or carry safe defaults, so existing data remains fully valid.

### 5.2 Running Migrations

```bash
php artisan migrate
```

Expected output:
```
Running migrations...
  2026_03_07_000001_add_versioning_fields_to_content_versions_table .... DONE
  2026_03_07_000002_add_draft_version_id_to_contents_table ............. DONE
  2026_03_07_000003_create_content_drafts_table ......................... DONE
  2026_03_07_000004_create_scheduled_publishes_table .................... DONE
```

### 5.3 Migration Details

#### Migration 1 — `add_versioning_fields_to_content_versions_table`

New columns on `content_versions`:

| Column | Type | Default | Description |
|---|---|---|---|
| `label` | `string` nullable | `null` | Human-readable version name |
| `status` | `string` | `'draft'` | `draft \| published \| archived \| scheduled` |
| `parent_version_id` | `ulid` nullable FK → `content_versions.id` | `null` | Branch parent |
| `scheduled_at` | `timestamp` nullable | `null` | Scheduled publish time |
| `content_hash` | `string(64)` nullable | `null` | SHA-256 of content fields |
| `locked_by` | `ulid` nullable | `null` | User currently editing |
| `locked_at` | `timestamp` nullable | `null` | Lock timestamp |

> **Existing rows:** All new columns are nullable or defaulted, so existing rows remain valid. You may want to backfill `status` — see [§5.4 Backfill Existing Content](#54-backfill-existing-content-optional).

#### Migration 2 — `add_draft_version_id_to_contents_table`

New columns on `contents`:

| Column | Type | Default | Description |
|---|---|---|---|
| `draft_version_id` | `ulid` nullable FK → `content_versions.id` | `null` | Active draft version pointer |
| `scheduled_publish_at` | `timestamp` nullable | `null` | Convenience column for next scheduled publish |

#### Migration 3 — `create_content_drafts_table`

New table for auto-save buffers:

| Column | Type | Description |
|---|---|---|
| `id` | ULID PK | |
| `content_id` | ULID FK → `contents.id` (cascade delete) | |
| `user_id` | ULID FK → `users.id` | |
| `title` | `string` | |
| `excerpt` | `text` nullable | |
| `body` | `longText` | |
| `body_format` | `string` default `'markdown'` | |
| `structured_fields` | `json` nullable | |
| `seo_data` | `json` nullable | |
| `blocks_snapshot` | `json` nullable | Serialized block array |
| `base_version_id` | `ulid` nullable FK → `content_versions.id` (null on delete) | |
| `last_saved_at` | `timestamp` | |
| `save_count` | `unsignedInteger` default `0` | |
| `created_at`, `updated_at` | `timestamps` | |

**Unique constraint:** `(content_id, user_id)` — one auto-save draft per user per content item.

#### Migration 4 — `create_scheduled_publishes_table`

New table for scheduled publish records:

| Column | Type | Description |
|---|---|---|
| `id` | ULID PK | |
| `content_id` | ULID FK → `contents.id` (cascade delete) | |
| `version_id` | ULID FK → `content_versions.id` (cascade delete) | |
| `scheduled_by` | ULID FK → `users.id` | |
| `publish_at` | `timestamp` | When to publish |
| `status` | `string` default `'pending'` | `pending \| published \| cancelled \| failed` |
| `notes` | `text` nullable | Optional admin notes |
| `created_at`, `updated_at` | `timestamps` | |

**Index:** `(status, publish_at)` — for efficient due-schedule queries.

---

### 5.4 Backfill Existing Content (Optional)

After migrating, existing `content_versions` rows will have `status = 'draft'` (the column default). To correctly mark existing published and archived versions:

**Via SQL:**
```sql
-- Mark versions pointed to by current_version_id as 'published'
UPDATE content_versions cv
INNER JOIN contents c ON c.current_version_id = cv.id
SET cv.status = 'published';

-- Mark all other versions as 'archived'
UPDATE content_versions
SET status = 'archived'
WHERE status = 'draft'
AND id NOT IN (SELECT draft_version_id FROM contents WHERE draft_version_id IS NOT NULL);
```

**Via Artisan tinker:**
```php
// Mark current versions as published
Content::with('currentVersion')->each(function (Content $content) {
    $content->currentVersion?->update(['status' => 'published']);
});

// Archive remaining drafts (that are not active drafts)
ContentVersion::where('status', 'draft')
    ->whereNotIn('id', Content::whereNotNull('draft_version_id')->pluck('draft_version_id'))
    ->update(['status' => 'archived']);
```

---

### 5.5 New Dependencies

#### PHP (Composer)

| Package | Version | Purpose | Install |
|---|---|---|---|
| `jfcherng/php-diff` | `^6.0` | Line/word-level text diffing (MIT) | `composer require jfcherng/php-diff` |

#### JavaScript (npm)

| Package | Version | Purpose | Install |
|---|---|---|---|
| `diff2html` | `^3.4` | Render unified/side-by-side diffs in Vue (MIT) | `npm install diff2html` |

---

### 5.6 Queue Configuration

The scheduled publishing job requires a running queue worker:

```bash
# Development
php artisan queue:work

# Production (Supervisor recommended)
php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=60
```

The cron safety net requires Laravel's scheduler. Add to server crontab:
```cron
* * * * * cd /var/www/numen && php artisan schedule:run >> /dev/null 2>&1
```

---

### 5.7 Environment Variables

No new required environment variables. Optional:

| Variable | Default | Description |
|---|---|---|
| `NUMEN_PIPELINE_AUTO_PUBLISH` | `false` | Auto-publish AI pipeline output (skips human review) |

---

*Numen 0.2.0 — all versioning additions are backwards-compatible with existing content data.*
