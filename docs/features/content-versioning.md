# Content Versioning & Draft System

> **Feature branch:** `feature/versioning`
> **Status:** Implementation Complete
> **Numen version:** 0.x

---

## Table of Contents

1. [Overview](#1-overview)
2. [API Reference](#2-api-reference)
3. [Admin Guide](#3-admin-guide)
4. [Developer Guide](#4-developer-guide)
5. [Migration Guide](#5-migration-guide)

---

## 1. Overview

Numen's Content Versioning & Draft System gives you a complete audit trail for every content item — plus the tools to draft, review, schedule, and roll back changes with confidence.

### What It Does

| Capability | Description |
|---|---|
| **Version history** | Every publish creates an immutable snapshot with a number, author, and timestamp |
| **Named versions** | Label important snapshots — "v1.0 Launch Copy", "SEO Refresh Q2" |
| **Auto-save drafts** | Changes are buffered per-user without polluting version history |
| **One-click rollback** | Restore any historical version; a new version is created and published instantly |
| **Side-by-side diff** | Compare any two versions with word-level and line-level highlighting |
| **Scheduled publishing** | Set a future date/time; the system publishes automatically |
| **Version branching** | Work on next version while current version stays live |
| **AI provenance** | Pipeline-generated versions carry full token/model/cost metadata |

### Core Concepts

```
Content
  ├── current_version_id  → live/published version (what visitors see)
  ├── draft_version_id    → active in-progress draft
  └── autosaveDraft       → per-user ephemeral buffer (not a version)

ContentVersion
  ├── status: draft | published | archived | scheduled
  ├── label: optional human-readable name
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
```

---

## 2. API Reference

All endpoints are under `/api/v1/` and require `auth:sanctum` (Bearer token).

### Authentication

```bash
# Include with every request
-H "Authorization: Bearer {your-sanctum-token}"
-H "Accept: application/json"
-H "Content-Type: application/json"
```

---

### 2.1 List Versions

Returns a paginated list of all versions for a content item (most recent first).

```
GET /api/v1/content/{content}/versions
```

**Path params:**
| Param | Type | Description |
|---|---|---|
| `content` | ULID | Content item ID |

**Query params:**
| Param | Type | Default | Description |
|---|---|---|---|
| `page` | integer | 1 | Pagination page |

**Response `200`:**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": "01hwzx...",
      "version_number": 3,
      "label": "SEO Refresh",
      "status": "published",
      "author_type": "human",
      "author_id": "01hwzy...",
      "change_reason": "Updated meta description",
      "pipeline_run_id": null,
      "quality_score": "0.87",
      "seo_score": "0.91",
      "scheduled_at": null,
      "content_hash": "a3f9d2...",
      "parent_version_id": "01hwzw...",
      "created_at": "2026-03-07T10:00:00.000000Z",
      "pipeline_run": null
    }
  ],
  "per_page": 25,
  "total": 3
}
```

**curl:**
```bash
curl -s \
  -H "Authorization: Bearer {token}" \
  "https://your-numen.app/api/v1/content/01hwzx.../versions"
```

---

### 2.2 Get Version

Returns a single version with full content, blocks, and pipeline run.

```
GET /api/v1/content/{content}/versions/{version}
```

**Response `200`:**
```json
{
  "data": {
    "id": "01hwzx...",
    "version_number": 3,
    "label": "SEO Refresh",
    "status": "published",
    "title": "10 Tips for Better SEO",
    "excerpt": "A practical guide to improving...",
    "body": "# Introduction\n\nGreat SEO starts with...",
    "body_format": "markdown",
    "structured_fields": { "hero_image": "...", "cta_text": "Read more" },
    "seo_data": { "meta_title": "...", "meta_description": "..." },
    "author_type": "human",
    "author_id": "01hwzy...",
    "change_reason": "Updated meta description",
    "quality_score": "0.87",
    "seo_score": "0.91",
    "content_hash": "a3f9d2...",
    "parent_version": { "id": "01hwzw...", "version_number": 2, "label": null },
    "blocks": [
      { "id": "...", "type": "hero", "sort_order": 0, "data": { "heading": "..." } }
    ],
    "pipeline_run": null,
    "created_at": "2026-03-07T10:00:00.000000Z"
  }
}
```

**curl:**
```bash
curl -s \
  -H "Authorization: Bearer {token}" \
  "https://your-numen.app/api/v1/content/01hwzx.../versions/01hwzx..."
```

---

### 2.3 Create Draft

Creates a new draft version, branched from the current live version. Sets `content.draft_version_id` to the new draft.

```
POST /api/v1/content/{content}/versions/draft
```

**Request body:** none

**Response `201`:**
```json
{
  "data": {
    "id": "01hwzz...",
    "version_number": 4,
    "status": "draft",
    "title": "10 Tips for Better SEO",
    "body": "# Introduction\n\n...",
    "parent_version_id": "01hwzx...",
    "created_at": "2026-03-07T11:00:00.000000Z"
  }
}
```

**curl:**
```bash
curl -s -X POST \
  -H "Authorization: Bearer {token}" \
  "https://your-numen.app/api/v1/content/01hwzx.../versions/draft"
```

---

### 2.4 Update Draft

Updates the content of a draft version. Only `draft` status versions can be edited (returns `422` otherwise).

```
PATCH /api/v1/content/{content}/versions/{version}
```

**Request body (all fields optional):**
```json
{
  "title": "10 Tips for Better SEO in 2026",
  "excerpt": "An updated practical guide...",
  "body": "# Introduction\n\nGreat SEO starts with...",
  "body_format": "markdown",
  "structured_fields": { "hero_image": "new-url.jpg" },
  "seo_data": { "meta_title": "Updated title" },
  "change_reason": "Annual refresh"
}
```

**Field validation:**
| Field | Rules |
|---|---|
| `title` | string, max 500 |
| `excerpt` | nullable, string, max 2000 |
| `body` | string |
| `body_format` | `markdown` \| `html` \| `blocks` |
| `structured_fields` | nullable array |
| `seo_data` | nullable array |
| `change_reason` | nullable string, max 255 |

**Response `200`:**
```json
{ "data": { /* updated version object */ } }
```

**curl:**
```bash
curl -s -X PATCH \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"title": "Updated Title", "change_reason": "Annual refresh"}' \
  "https://your-numen.app/api/v1/content/01hwzx.../versions/01hwzz..."
```

---

### 2.5 Label a Version

Adds a human-readable name to a version and clears any auto-save buffer for the content.

```
POST /api/v1/content/{content}/versions/{version}/label
```

**Request body:**
```json
{ "label": "v2.0 SEO Update" }
```

**Validation:** `label` required, string, max 255.

**Response `200`:**
```json
{ "data": { /* version with label applied */ } }
```

**curl:**
```bash
curl -s -X POST \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"label": "v2.0 SEO Update"}' \
  "https://your-numen.app/api/v1/content/01hwzx.../versions/01hwzz.../label"
```

---

### 2.6 Publish a Version

Makes a version live. The previously published version is automatically archived.

```
POST /api/v1/content/{content}/versions/{version}/publish
```

**Request body:** none

**Side effects:**
- Previous `published` version → `archived`
- This version → `published`
- `content.current_version_id` → this version
- `content.status` → `published`
- `content.published_at` → now
- If this was the draft version, `content.draft_version_id` is cleared
- Fires `ContentPublished` event

**Response `200`:**
```json
{
  "message": "Published",
  "data": { /* updated content object */ }
}
```

**curl:**
```bash
curl -s -X POST \
  -H "Authorization: Bearer {token}" \
  "https://your-numen.app/api/v1/content/01hwzx.../versions/01hwzz.../publish"
```

---

### 2.7 Schedule a Version

Schedules a version to go live at a future date/time. Cancels any existing pending schedule.

```
POST /api/v1/content/{content}/versions/{version}/schedule
```

**Request body:**
```json
{
  "publish_at": "2026-03-15T09:00:00Z",
  "notes": "Goes live with the Q1 campaign launch"
}
```

**Validation:**
| Field | Rules |
|---|---|
| `publish_at` | required, valid date, must be in the future (`after:now`) |
| `notes` | nullable, string, max 500 |

**Side effects:**
- Any existing `pending` schedule for this content → `cancelled`
- This version `status` → `scheduled`
- This version `scheduled_at` → `publish_at` value
- `content.status` → `scheduled`
- `content.scheduled_publish_at` → `publish_at` value
- A `ScheduledPublish` record is created
- A `PublishScheduledContent` job is dispatched with delay

**Response `201`:**
```json
{
  "data": {
    "id": "01hwza...",
    "content_id": "01hwzx...",
    "version_id": "01hwzz...",
    "scheduled_by": "01hwzy...",
    "publish_at": "2026-03-15T09:00:00.000000Z",
    "status": "pending",
    "notes": "Goes live with the Q1 campaign launch",
    "created_at": "2026-03-07T11:00:00.000000Z"
  }
}
```

**curl:**
```bash
curl -s -X POST \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"publish_at": "2026-03-15T09:00:00Z", "notes": "Q1 launch"}' \
  "https://your-numen.app/api/v1/content/01hwzx.../versions/01hwzz.../schedule"
```

---

### 2.8 Cancel Schedule

Cancels the pending scheduled publish for a version.

```
DELETE /api/v1/content/{content}/versions/{version}/schedule
```

**Request body:** none

**Side effects:**
- All `pending` schedules for this content → `cancelled`
- This version `status` → `draft`
- This version `scheduled_at` → `null`
- `content.status` → `draft`
- `content.scheduled_publish_at` → `null`

**Response `200`:**
```json
{ "message": "Schedule cancelled" }
```

**curl:**
```bash
curl -s -X DELETE \
  -H "Authorization: Bearer {token}" \
  "https://your-numen.app/api/v1/content/01hwzx.../versions/01hwzz.../schedule"
```

---

### 2.9 Rollback

Creates a new version from a historical snapshot and immediately publishes it. The historical version is not modified.

```
POST /api/v1/content/{content}/versions/{version}/rollback
```

**Request body:** none

**What happens:**
1. A new version is created (next `version_number`) copying all content from `{version}`
2. `change_reason` is set to `"Rollback to v{N} (Label)"` 
3. All blocks are cloned from the target version
4. The new version is immediately published
5. Previous published version is archived

**Response `201`:**
```json
{
  "data": {
    "id": "01hwzb...",
    "version_number": 5,
    "status": "published",
    "change_reason": "Rollback to v2 (Original Launch Copy)",
    "parent_version_id": "01hwzv...",
    "created_at": "2026-03-07T11:30:00.000000Z"
  }
}
```

**curl:**
```bash
curl -s -X POST \
  -H "Authorization: Bearer {token}" \
  "https://your-numen.app/api/v1/content/01hwzx.../versions/01hwzv.../rollback"
```

---

### 2.10 Branch

Creates a new draft branched from any version (not just the current live one). Useful for preparing the next version while the current version stays live.

```
POST /api/v1/content/{content}/versions/{version}/branch
```

**Request body (optional):**
```json
{ "label": "v3.0 Redesign" }
```

**Response `201`:**
```json
{
  "data": {
    "id": "01hwzc...",
    "version_number": 6,
    "status": "draft",
    "label": "v3.0 Redesign",
    "parent_version_id": "01hwzx...",
    "created_at": "2026-03-07T12:00:00.000000Z"
  }
}
```

**curl:**
```bash
curl -s -X POST \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"label": "v3.0 Redesign"}' \
  "https://your-numen.app/api/v1/content/01hwzx.../versions/01hwzx.../branch"
```

---

### 2.11 Compare Versions (Diff)

Returns a structured diff between any two versions of the same content item.

```
GET /api/v1/content/{content}/diff?version_a={id}&version_b={id}
```

**Query params:**
| Param | Type | Required | Description |
|---|---|---|---|
| `version_a` | ULID | ✓ | "Before" version ID |
| `version_b` | ULID | ✓ | "After" version ID |

Both versions must belong to the specified content item.

**Response `200`:**
```json
{
  "data": {
    "version_a": {
      "id": "01hwzw...",
      "version_number": 2,
      "label": "Original Launch Copy",
      "created_at": "2026-03-01T09:00:00.000000Z"
    },
    "version_b": {
      "id": "01hwzx...",
      "version_number": 3,
      "label": "SEO Refresh",
      "created_at": "2026-03-07T10:00:00.000000Z"
    },
    "has_changes": true,
    "summary": "title changed, body: +5/-2 lines, 1 SEO fields changed",
    "fields": {
      "title": {
        "type": "changed",
        "old": "10 Tips for Better SEO",
        "new": "10 Tips for Better SEO in 2026",
        "hunks": [ /* word-level diff tokens */ ]
      },
      "body": {
        "type": "changed",
        "hunks": [ /* line-level diff opcodes */ ],
        "stats": {
          "lines_added": 5,
          "lines_removed": 2,
          "words_old": 342,
          "words_new": 368
        }
      }
    },
    "blocks": [
      {
        "type": "modified",
        "position": 2,
        "old": { "type": "callout", "data": { "text": "Old CTA" } },
        "new": { "type": "callout", "data": { "text": "Updated CTA" } }
      }
    ],
    "seo": {
      "meta_description": {
        "old": "A guide to SEO",
        "new": "The definitive 2026 guide to SEO"
      }
    }
  }
}
```

**curl:**
```bash
curl -s \
  -H "Authorization: Bearer {token}" \
  "https://your-numen.app/api/v1/content/01hwzx.../diff?version_a=01hwzw...&version_b=01hwzx..."
```

---

### 2.12 Auto-Save

#### Save (Upsert)

Saves or updates the auto-save buffer for the authenticated user. One draft per user per content item. Does **not** create a `ContentVersion`.

```
POST /api/v1/content/{content}/autosave
```

**Request body (all fields optional):**
```json
{
  "title": "Work in progress title",
  "body": "# Draft content...",
  "body_format": "markdown",
  "blocks_snapshot": [ /* serialized block array */ ],
  "base_version_id": "01hwzx..."
}
```

**Validation:**
| Field | Rules |
|---|---|
| `title` | sometimes, string, max 500 |
| `excerpt` | nullable, string, max 2000 |
| `body` | sometimes, string |
| `body_format` | `markdown` \| `html` \| `blocks` |
| `structured_fields` | nullable array |
| `seo_data` | nullable array |
| `blocks_snapshot` | nullable array |
| `base_version_id` | nullable, must exist in `content_versions` |

**Response `200`:**
```json
{
  "data": {
    "id": "01hwzd...",
    "content_id": "01hwzx...",
    "user_id": "01hwzy...",
    "title": "Work in progress title",
    "last_saved_at": "2026-03-07T11:05:33.000000Z",
    "save_count": 14
  }
}
```

**curl:**
```bash
curl -s -X POST \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"title": "WIP title", "body": "Draft content..."}' \
  "https://your-numen.app/api/v1/content/01hwzx.../autosave"
```

#### Get Auto-Save

```
GET /api/v1/content/{content}/autosave
```

Returns the current user's auto-save draft, or `null` if none exists.

**Response `200`:**
```json
{ "data": { /* draft object or null */ } }
```

**curl:**
```bash
curl -s \
  -H "Authorization: Bearer {token}" \
  "https://your-numen.app/api/v1/content/01hwzx.../autosave"
```

#### Discard Auto-Save

```
DELETE /api/v1/content/{content}/autosave
```

Deletes the current user's auto-save draft.

**Response `200`:**
```json
{ "message": "Auto-save discarded" }
```

**curl:**
```bash
curl -s -X DELETE \
  -H "Authorization: Bearer {token}" \
  "https://your-numen.app/api/v1/content/01hwzx.../autosave"
```

---

### Error Responses

| Code | Scenario |
|---|---|
| `401` | Missing or invalid Sanctum token |
| `404` | Content or version not found |
| `422` | Validation error, or trying to edit a non-draft version |

**Example `422`:**
```json
{
  "message": "Only draft versions can be edited.",
  "errors": {}
}
```

---

## 3. Admin Guide

### 3.1 Version History Panel

The **Version Sidebar** (`VersionSidebar.vue`) is a right-hand panel in the Content Editor showing a chronological timeline of all versions.

**Each version row shows:**
- Version number (e.g. `v3`)
- Label, if one has been set (e.g. `SEO Refresh`)
- Status badge — color-coded:
  - 🟢 `published` — currently live
  - 🟡 `draft` — in progress
  - 🔵 `scheduled` — queued for future publish
  - ⚫ `archived` — superseded
- Author (🤖 for AI-generated versions)
- Relative timestamp (e.g. "3 days ago")
- Action buttons: **Publish**, **Rollback**, **Branch**, **Diff**

Click any version row to **preview** its content without switching the live version.

---

### 3.2 Creating and Editing Drafts

1. Open a content item in the editor
2. Click **"New Draft"** — this calls `POST .../versions/draft` and creates a new draft version branched from the current live version
3. The editor loads the draft; the status indicator shows `DRAFT`
4. Edit content as normal — the **Auto-Save Indicator** saves every 2 seconds while you type
5. When ready to save a named snapshot, click **"Save Version"** and enter a label
6. To publish, click **"Publish"** in the version sidebar action buttons

> **Auto-Save Recovery:** If you close the browser mid-edit, the auto-save buffer is preserved. On next load, a banner appears: *"You have unsaved changes from [time]. Recover?"* Click **Recover** to restore, or **Discard** to start fresh from the last saved version.

---

### 3.3 Diff Viewer

Compare any two versions side-by-side:

1. In the Version Sidebar, click **"Diff"** on any version, **or** select two versions and click **"Compare"**
2. The **Version Diff Modal** opens with:
   - **Version selectors** — two dropdowns to choose version A (left/before) and version B (right/after)
   - **Summary bar** — e.g. "title changed, body: +5/-2 lines, 2 blocks added"
   - **Toggle** — switch between side-by-side and unified diff view
3. **Field diffs** — title and excerpt use **word-level** highlighting; body uses **line-level** with context
4. **Block diff** — visual cards show added/removed/modified blocks with block type icons
5. **SEO diff panel** — lists changed SEO metadata fields with old/new values

---

### 3.4 Scheduling a Publish

1. In the Version Sidebar, click **"Schedule"** next to the version you want to publish
2. The **Schedule Publish Modal** opens:
   - Pick a date and time using the date/time picker
   - The modal shows your timezone and the UTC equivalent
   - Add optional notes (visible in the admin audit trail)
3. Click **"Confirm Schedule"** — the version status changes to `scheduled` with a clock icon
4. To **cancel** a scheduled publish: click the clock icon next to a `scheduled` version and confirm cancellation

**What happens at publish time:**
- A Laravel queue job (`PublishScheduledContent`) fires at the exact scheduled time
- A cron command (`numen:publish-scheduled`) runs every minute as a safety net
- Whichever runs first wins; the other is a no-op

---

### 3.5 Rolling Back

1. In the Version Sidebar, click **"Rollback"** next to any historical version
2. Confirm the rollback in the dialog
3. Numen creates a **new version** (not modifying the historical one) with content copied from the target, and immediately publishes it
4. The rollback version appears at the top of the history with a `change_reason` like `"Rollback to v2 (Original Launch Copy)"`

---

### 3.6 AI-Generated Versions

Versions created by the AI pipeline are marked with a 🤖 icon. Each AI version carries:
- A link to the **Pipeline Run** that generated it (click to see full run details)
- Model(s) used, total tokens, and estimated cost
- Quality score and SEO score from the editorial/SEO stages
- The source brief

AI versions always start with `draft` status — they require human review and an explicit publish action. Unless `numen.pipeline.auto_publish` is enabled in config.

---

## 4. Developer Guide

### 4.1 VersioningService

**Namespace:** `App\Services\Versioning\VersioningService`

Inject via constructor or `app()`:

```php
use App\Services\Versioning\VersioningService;

class MyController extends Controller
{
    public function __construct(private VersioningService $versioning) {}
}
```

---

#### `createDraft(Content $content, ?ContentVersion $branchFrom = null): ContentVersion`

Creates a new draft version, branched from `$branchFrom` (or from the current published version if `null`). Copies all content fields and clones content blocks. Sets `content.draft_version_id`.

```php
// Branch from current live version
$draft = $versioning->createDraft($content);

// Branch from a specific historical version
$draft = $versioning->createDraft($content, $historicalVersion);
```

---

#### `autoSave(Content $content, User $user, array $data): ContentDraft`

Upserts the auto-save buffer for a given user/content pair. One record per user per content item. Increments `save_count` atomically. Does **not** create a `ContentVersion`.

```php
$draft = $versioning->autoSave($content, $request->user(), [
    'title' => 'New title',
    'body' => 'Updated body...',
    'last_saved_at' => now(),
]);
```

---

#### `saveVersion(ContentVersion $draft, string $label, ?string $changeReason = null): ContentVersion`

Promotes a draft to a named save point: sets the `label`, computes and stores `content_hash`, and clears the auto-save buffer.

```php
$namedVersion = $versioning->saveVersion($draft, 'v2.0 SEO Refresh', 'Annual SEO audit');
```

---

#### `publish(Content $content, ContentVersion $version): void`

Makes a version live:
1. Archives any currently published version
2. Sets this version to `published`
3. Updates `content.current_version_id`, `content.status`, `content.published_at`
4. Clears `content.draft_version_id` if this was the draft
5. Fires `ContentPublished` event

```php
$versioning->publish($content, $draftVersion);
```

---

#### `schedule(Content $content, ContentVersion $version, Carbon $publishAt, ?string $notes = null): ScheduledPublish`

Schedules a future publish. Cancels any existing pending schedule for the content.

```php
use Carbon\Carbon;

$schedule = $versioning->schedule(
    $content,
    $version,
    Carbon::parse('2026-03-15 09:00:00'),
    'Q1 campaign launch',
);
```

---

#### `rollback(Content $content, ContentVersion $targetVersion): ContentVersion`

Creates a new version copying all content from `$targetVersion`, clones its blocks, and immediately publishes it. The target version is unchanged.

```php
$restoredVersion = $versioning->rollback($content, $oldVersion);
// $restoredVersion is now live (status: published)
```

---

#### `diff(ContentVersion $versionA, ContentVersion $versionB): VersionDiff`

Compares two versions and returns a `VersionDiff` value object.

```php
$diff = $versioning->diff($versionA, $versionB);

if ($diff->hasChanges()) {
    echo $diff->summary(); // "title changed, body: +5/-2 lines"
}

// JSON-serializable for API responses
return response()->json(['data' => $diff]);
```

---

#### `branch(Content $content, ContentVersion $fromVersion, ?string $label = null): ContentVersion`

Creates a draft branched from any version (alias for `createDraft` with an optional label applied immediately). Use this when you want to prepare the next version while the current one stays live.

```php
$nextVersion = $versioning->branch($content, $liveVersion, 'v3.0 Redesign');
// Current live version is still live, $nextVersion is a draft
```

---

### 4.2 VersionDiff Value Object

**Namespace:** `App\Services\Versioning\VersionDiff`

| Property | Type | Description |
|---|---|---|
| `$versionA` | `ContentVersion` | "Before" version |
| `$versionB` | `ContentVersion` | "After" version |
| `$fieldDiffs` | `array` | Diffs for `title`, `excerpt`, `body` |
| `$blockDiffs` | `array` | Added/removed/modified blocks |
| `$seoDiffs` | `array` | Changed SEO metadata keys |

**Methods:**
- `hasChanges(): bool` — `true` if any field, block, or SEO data differs
- `summary(): string` — human-readable summary string
- `jsonSerialize(): array` — implements `\JsonSerializable`

---

### 4.3 ContentVersion Model

**Namespace:** `App\Models\ContentVersion`

#### Key fields

| Field | Type | Description |
|---|---|---|
| `version_number` | int | Auto-incrementing per content item |
| `label` | string\|null | Human-readable name |
| `status` | string | `draft` \| `published` \| `archived` \| `scheduled` |
| `parent_version_id` | ULID\|null | Branch parent |
| `author_type` | string | `human` \| `ai_agent` |
| `author_id` | string | User ULID or Pipeline ULID |
| `pipeline_run_id` | ULID\|null | AI provenance link |
| `ai_metadata` | array\|null | Models, tokens, cost |
| `scheduled_at` | datetime\|null | When to publish |
| `content_hash` | string\|null | SHA-256 of content fields |
| `locked_by` | ULID\|null | User currently editing |
| `locked_at` | datetime\|null | Lock timestamp |

#### Scopes

```php
ContentVersion::published()  // status = 'published'
ContentVersion::drafts()     // status = 'draft'
ContentVersion::scheduled()  // status = 'scheduled'
ContentVersion::labeled()    // label IS NOT NULL
```

#### Helper methods

```php
$version->isPublished();     // bool
$version->isDraft();         // bool
$version->isScheduled();     // bool
$version->isAiGenerated();   // bool — author_type === 'ai_agent'
$version->hasBlocks();       // bool — checks for related ContentBlock records
$version->computeHash();     // string — SHA-256 of content fields
```

#### Relations

```php
$version->content;           // BelongsTo Content
$version->blocks;            // HasMany ContentBlock (ordered by sort_order)
$version->pipelineRun;       // BelongsTo PipelineRun
$version->parentVersion;     // BelongsTo ContentVersion
$version->childVersions;     // HasMany ContentVersion
```

---

### 4.4 ContentDraft Model

**Namespace:** `App\Models\ContentDraft`

One record per `(content_id, user_id)` pair (unique constraint). Use `VersioningService::autoSave()` rather than creating directly.

#### Relations
```php
$draft->content;      // BelongsTo Content
$draft->user;         // BelongsTo User
$draft->baseVersion;  // BelongsTo ContentVersion (the version being edited from)
```

---

### 4.5 ScheduledPublish Model

**Namespace:** `App\Models\ScheduledPublish`

#### Scopes

```php
ScheduledPublish::pending()  // status = 'pending'
ScheduledPublish::due()      // pending + publish_at <= now()
```

#### Relations
```php
$schedule->content;    // BelongsTo Content
$schedule->version;    // BelongsTo ContentVersion
$schedule->scheduler;  // BelongsTo User (via scheduled_by)
```

---

### 4.6 Pipeline Integration

Every AI pipeline run that generates content should create a version with full provenance. Here's the pattern:

```php
$version = $content->versions()->create([
    'version_number' => $content->versions()->max('version_number') + 1,
    'title' => $generatedContent['title'],
    'body' => $generatedContent['body'],
    'body_format' => 'markdown',
    'author_type' => 'ai_agent',
    'author_id' => $pipelineRun->pipeline_id,
    'change_reason' => "Pipeline run: {$pipelineRun->pipeline->name}",
    'pipeline_run_id' => $pipelineRun->id,
    'ai_metadata' => [
        'pipeline_run_id' => $pipelineRun->id,
        'models_used' => $pipelineRun->generationLogs->pluck('model')->unique()->values(),
        'total_tokens' => $pipelineRun->generationLogs->sum('total_tokens'),
        'total_cost_usd' => $pipelineRun->generationLogs->sum('cost_usd'),
    ],
    'quality_score' => $generatedContent['quality_score'] ?? null,
    'seo_score' => $generatedContent['seo_score'] ?? null,
    'status' => 'draft',  // AI versions always start as draft
    'parent_version_id' => $content->current_version_id,
]);

// Compute hash after blocks are attached
$version->update(['content_hash' => $version->computeHash()]);
```

**Auto-publish config:** Set `NUMEN_PIPELINE_AUTO_PUBLISH=true` in `.env` to bypass human review for pipeline-generated content.

---

### 4.7 Events

Subscribe to these events for cache invalidation, webhooks, or notifications:

| Event | Class | Payload |
|---|---|---|
| Version published | `App\Events\Content\ContentPublished` | `$content` |
| Content scheduled | `App\Events\ContentScheduled` | `$content, $version, $publish_at` |
| Content rolled back | `App\Events\ContentRolledBack` | `$content, $new_version, $target_version` |
| Draft auto-saved | `App\Events\ContentDraftAutoSaved` | `$content, $user, $draft` |

```php
// Register in EventServiceProvider or with #[AsEventListener]
Event::listen(ContentPublished::class, function (ContentPublished $event) {
    Cache::tags(['content', $event->content->slug])->flush();
});
```

---

### 4.8 Scheduled Publishing — How It Works

Two mechanisms fire in parallel (both are idempotent):

**1. Delayed Queue Job** (primary — fires at exact time)
```
PublishScheduledContent::dispatch($scheduleId)->delay($publishAt)
```
- 3 retries with 60s backoff on failure
- Checks `status === 'pending'` before acting; no-ops if already cancelled

**2. Artisan Cron** (safety net — runs every minute)
```bash
php artisan numen:publish-scheduled
```
- Finds all `ScheduledPublish` records where `status = pending AND publish_at <= now()`
- Processes them through `VersioningService::publish()`
- Configured in `routes/console.php` with `->withoutOverlapping()`

**Ensure your worker is running:**
```bash
php artisan queue:work --queue=default
```

---

## 5. Migration Guide

### 5.1 Overview

The versioning system adds **4 new migrations** — all additive. No existing tables are dropped or modified. All new columns are nullable or carry safe defaults, so existing data is fully compatible.

### 5.2 Run Migrations

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

Adds versioning columns to the existing `content_versions` table:

| Column | Type | Default | Description |
|---|---|---|---|
| `label` | `string` nullable | `null` | Human-readable version name |
| `status` | `string` | `'draft'` | `draft \| published \| archived \| scheduled` |
| `parent_version_id` | `ulid` nullable FK → `content_versions.id` | `null` | Branch parent |
| `scheduled_at` | `timestamp` nullable | `null` | Scheduled publish time |
| `content_hash` | `string(64)` nullable | `null` | SHA-256 of content fields |
| `locked_by` | `ulid` nullable | `null` | User currently editing |
| `locked_at` | `timestamp` nullable | `null` | Lock timestamp |

> **Existing rows:** All new columns are nullable, so existing `content_versions` rows remain valid. The `status` column defaults to `'draft'` — you may want to manually backfill existing published versions with `UPDATE content_versions SET status = 'published' WHERE id IN (SELECT current_version_id FROM contents WHERE current_version_id IS NOT NULL)`.

#### Migration 2 — `add_draft_version_id_to_contents_table`

Adds to the `contents` table:

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
| `base_version_id` | ULID nullable FK → `content_versions.id` (null on delete) | |
| `last_saved_at` | `timestamp` | |
| `save_count` | `unsignedInteger` default `0` | |
| `timestamps` | | `created_at`, `updated_at` |

**Unique constraint:** `(content_id, user_id)` — one draft per user per content item.

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
| `timestamps` | | `created_at`, `updated_at` |

### 5.4 Backfill Existing Content (Optional)

After migrating, existing content items will have `status = null` on their `content_versions` rows. To backfill:

```sql
-- Mark all versions pointed to by current_version_id as 'published'
UPDATE content_versions cv
JOIN contents c ON c.current_version_id = cv.id
SET cv.status = 'published';

-- Mark all remaining versions as 'archived'
UPDATE content_versions
SET status = 'archived'
WHERE status IS NULL OR status = '';
```

Or via Artisan tinker:
```php
// Publish current versions
Content::with('currentVersion')->each(function ($content) {
    $content->currentVersion?->update(['status' => 'published']);
});

// Archive the rest
ContentVersion::whereNull('status')->orWhere('status', '')->update(['status' => 'archived']);
```

### 5.5 New Composer Dependencies

| Package | Purpose | Install |
|---|---|---|
| `jfcherng/php-diff` | Line/word-level diff computation (MIT) | `composer require jfcherng/php-diff` |

### 5.6 New NPM Dependencies

| Package | Purpose | Install |
|---|---|---|
| `diff2html` | Render unified/side-by-side diffs in Vue (MIT) | `npm install diff2html` |

### 5.7 Queue Configuration

The scheduled publishing job requires a running queue worker:

```bash
# Development
php artisan queue:work

# Production (Supervisor recommended)
php artisan queue:work --queue=default --sleep=3 --tries=3
```

The cron safety net requires Laravel's scheduler to be running. Add to crontab:

```cron
* * * * * cd /path/to/numen && php artisan schedule:run >> /dev/null 2>&1
```

---

*Numen 0.x — all additions in this feature are backwards-compatible.*
