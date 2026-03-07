# Taxonomy & Content Organization

**Version:** 1.0.0  
**Added:** 2026-03-07  
**Status:** Stable

Numen's taxonomy system lets you organize content into structured, hierarchical vocabularies — Categories, Tags, Topics, or any custom grouping you define. Content can belong to multiple terms across multiple vocabularies, and the AI pipeline can auto-categorize content for you.

---

## Table of Contents

1. [Concepts](#concepts)
2. [API Reference](#api-reference)
   - [Vocabularies](#vocabularies)
   - [Terms](#terms)
   - [Content ↔ Terms](#content--terms)
3. [Admin Guide](#admin-guide)
4. [Developer Guide](#developer-guide)
   - [Models & Relationships](#models--relationships)
   - [Service Layer](#service-layer)
   - [AI Auto-Categorization](#ai-auto-categorization)
   - [Pipeline Integration](#pipeline-integration)
   - [Extending Taxonomy](#extending-taxonomy)
5. [Migration Guide](#migration-guide)

---

## Concepts

### Vocabulary

A **vocabulary** defines a classification scheme for your content. Think of it as the container:

| Vocabulary | Example Terms |
|------------|---------------|
| Categories | Technology, Design, Business |
| Tags | laravel, php, tutorial, video |
| Topics | Getting Started, Advanced, Reference |

Vocabularies are scoped to a **Space** and can be configured to:
- Allow or disallow hierarchical nesting (`hierarchy: true/false`)
- Allow one or multiple terms per content (`allow_multiple: true/false`)

### Term

A **term** is a single classification value within a vocabulary. Terms can be nested into trees using a `parent_id` relationship. For example:

```
Technology (depth 0)
├── Web Development (depth 1)
│   ├── Laravel (depth 2)
│   └── Vue.js (depth 2)
└── Mobile (depth 1)
```

Each term has a slug that is unique **within its vocabulary** (so `news` can exist in both "Categories" and "Topics").

### Content ↔ Term Assignment

The `content_taxonomy` pivot table links content to terms. Each assignment can record:

- **`sort_order`** — ordering of terms within a content item
- **`auto_assigned`** — whether the AI pipeline assigned this term
- **`confidence`** — AI confidence score (0.0–1.0) when auto-assigned

---

## API Reference

All endpoints are prefixed with `/api/v1/`. Pass the `X-Space` header to scope requests to a space (defaults to `default`).

### Authentication

| Endpoint Type | Auth Required |
|---------------|---------------|
| Read (GET) | No (public) |
| Write (POST / PUT / DELETE) | Yes — Bearer token via Sanctum |

```http
Authorization: Bearer <your-token>
X-Space: my-space-slug
```

---

### Vocabularies

#### List vocabularies

```http
GET /api/v1/taxonomies
X-Space: my-space
```

Returns all vocabularies for the space, ordered by `sort_order`.

**Response:**
```json
{
  "data": [
    {
      "id": "01hx...",
      "space_id": "01hy...",
      "name": "Categories",
      "slug": "categories",
      "description": "Main content categories",
      "hierarchy": true,
      "allow_multiple": true,
      "settings": null,
      "sort_order": 0,
      "terms_count": 12,
      "created_at": "2026-03-07T08:00:00+00:00",
      "updated_at": "2026-03-07T08:00:00+00:00"
    }
  ]
}
```

---

#### Show vocabulary + tree

```http
GET /api/v1/taxonomies/{vocabSlug}
X-Space: my-space
```

Returns the vocabulary metadata along with its full term tree.

**Response:**
```json
{
  "data": {
    "vocabulary": { "id": "01hx...", "name": "Categories", ... },
    "tree": [
      {
        "id": "01ha...",
        "name": "Technology",
        "slug": "technology",
        "depth": 0,
        "children": [
          {
            "id": "01hb...",
            "name": "Laravel",
            "slug": "laravel",
            "depth": 1,
            "children": []
          }
        ]
      }
    ]
  }
}
```

---

#### Create vocabulary

```http
POST /api/v1/taxonomies
Authorization: Bearer <token>
X-Space: my-space
Content-Type: application/json
```

**Request body:**
```json
{
  "name": "Topics",
  "slug": "topics",
  "description": "Content topics for navigation",
  "hierarchy": true,
  "allow_multiple": true,
  "sort_order": 1
}
```

> **Note:** The space is derived from the `X-Space` header — `space_id` is never accepted in the request body to prevent cross-space privilege escalation.

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | ✅ | Max 255 chars |
| `slug` | string | No | Auto-generated from name if omitted. Pattern: `^[a-z0-9]+(?:-[a-z0-9]+)*$` |
| `description` | string | No | Max 5000 chars |
| `hierarchy` | boolean | No | Default: `true` |
| `allow_multiple` | boolean | No | Default: `true` |
| `settings` | object | No | Arbitrary JSON for extensions |
| `sort_order` | integer | No | Default: `0`. Range: 0–9999 |

**Response:** `201 Created` with the created `VocabularyResource`.

---

#### Update vocabulary

```http
PUT /api/v1/taxonomies/{id}
Authorization: Bearer <token>
X-Space: my-space
Content-Type: application/json
```

All fields are optional (partial update). Same validation rules as create.

**Response:** Updated `VocabularyResource`.

---

#### Delete vocabulary

```http
DELETE /api/v1/taxonomies/{id}
Authorization: Bearer <token>
X-Space: my-space
```

Deletes the vocabulary and **all its terms** (cascade). Content assignments are also removed.

**Response:**
```json
{ "data": { "deleted": true } }
```

---

### Terms

#### List terms (flat)

```http
GET /api/v1/taxonomies/{vocabSlug}/terms
X-Space: my-space
```

Returns all terms in a vocabulary, ordered by `sort_order` then `name`.

#### List terms (tree)

```http
GET /api/v1/taxonomies/{vocabSlug}/terms?tree=1
X-Space: my-space
```

Returns the same data as a nested tree structure (see `TaxonomyTermTreeResource`).

---

#### Show term

```http
GET /api/v1/taxonomies/{vocabSlug}/terms/{termSlug}
X-Space: my-space
```

**Response:**
```json
{
  "data": {
    "id": "01ha...",
    "vocabulary_id": "01hx...",
    "parent_id": null,
    "name": "Technology",
    "slug": "technology",
    "description": null,
    "path": "/01ha...",
    "depth": 0,
    "sort_order": 0,
    "metadata": null,
    "content_count": 42,
    "created_at": "2026-03-07T08:00:00+00:00",
    "updated_at": "2026-03-07T08:00:00+00:00"
  }
}
```

---

#### List content for a term

```http
GET /api/v1/taxonomies/{vocabSlug}/terms/{termSlug}/content
X-Space: my-space
```

**Query parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `per_page` | integer | `20` | Items per page (max 100) |
| `include_descendants` | boolean | `false` | Include content from child terms |

**Example — fetch all Laravel content including child terms:**
```http
GET /api/v1/taxonomies/categories/terms/technology/content?include_descendants=true&per_page=10
```

---

#### Create term

```http
POST /api/v1/taxonomies/{vocabId}/terms
Authorization: Bearer <token>
X-Space: my-space
Content-Type: application/json
```

**Request body:**
```json
{
  "name": "Laravel",
  "slug": "laravel",
  "parent_id": "01hb...",
  "description": "The Laravel PHP framework",
  "sort_order": 0,
  "metadata": {
    "color": "#FF2D20",
    "icon": "laravel"
  }
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | ✅ | Max 255 chars |
| `slug` | string | No | Auto-generated from name; unique within vocabulary |
| `parent_id` | string | No | Must belong to the same vocabulary |
| `description` | string | No | Max 5000 chars |
| `sort_order` | integer | No | Default: `0`. Range: 0–9999 |
| `metadata` | object | No | Arbitrary JSON (colors, icons, SEO data, etc.) |

**Response:** `201 Created` with the created `TaxonomyTermResource`.

---

#### Update term

```http
PUT /api/v1/terms/{id}
Authorization: Bearer <token>
X-Space: my-space
Content-Type: application/json
```

Partial update — only include fields you want to change. Same field rules as create.

When `parent_id` is changed, the materialized `path` and `depth` are automatically recomputed for the term and all its descendants.

---

#### Delete term

```http
DELETE /api/v1/terms/{id}?child_strategy=reparent
Authorization: Bearer <token>
X-Space: my-space
```

| Parameter | Value | Behavior |
|-----------|-------|----------|
| `child_strategy` | `reparent` (default) | Child terms are moved up to the deleted term's parent |
| `child_strategy` | `cascade` | Child terms and the entire subtree are deleted |

**Response:**
```json
{ "data": { "deleted": true } }
```

---

#### Move term

Changes a term's parent (drag-and-drop reparenting). Recomputes paths for the entire affected subtree.

```http
POST /api/v1/terms/{id}/move
Authorization: Bearer <token>
X-Space: my-space
Content-Type: application/json
```

```json
{
  "parent_id": "01hc..."
}
```

Pass `"parent_id": null` to promote the term to root level.

---

#### Reorder siblings

Updates `sort_order` for a batch of terms (drag-and-drop ordering).

```http
POST /api/v1/terms/reorder
Authorization: Bearer <token>
X-Space: my-space
Content-Type: application/json
```

```json
{
  "ordering": {
    "01ha...": 0,
    "01hb...": 1,
    "01hc...": 2
  }
}
```

All term IDs in the `ordering` map must belong to the current space, or the request is rejected with `403`.

**Response:**
```json
{ "data": { "reordered": true } }
```

---

### Content ↔ Terms

#### List terms for a content item

```http
GET /api/v1/content/{slug}/terms
```

Returns all taxonomy terms assigned to the published content item.

---

#### Assign terms (additive)

Adds terms without removing existing ones.

```http
POST /api/v1/content/{id}/terms
Authorization: Bearer <token>
X-Space: my-space
Content-Type: application/json
```

```json
{
  "assignments": [
    {
      "term_id": "01ha...",
      "sort_order": 0
    },
    {
      "term_id": "01hb...",
      "sort_order": 1,
      "auto_assigned": false
    }
  ]
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `assignments[].term_id` | string | ✅ | Must belong to the same space |
| `assignments[].sort_order` | integer | No | Default: `0` |
| `assignments[].auto_assigned` | boolean | No | Default: `false` |
| `assignments[].confidence` | float | No | AI confidence score (0.0–1.0) |

---

#### Sync terms (replace)

Replaces all existing term assignments with the provided list.

```http
PUT /api/v1/content/{id}/terms
Authorization: Bearer <token>
X-Space: my-space
Content-Type: application/json
```

```json
{
  "term_ids": ["01ha...", "01hb..."]
}
```

Pass an empty array (`"term_ids": []`) to remove all term assignments.

---

#### Remove a single term

```http
DELETE /api/v1/content/{id}/terms/{termId}
Authorization: Bearer <token>
X-Space: my-space
```

**Response:**
```json
{ "data": { "removed": true } }
```

---

#### Trigger AI auto-categorization

Dispatches a background job to analyze the content and auto-assign matching terms.

```http
POST /api/v1/content/{id}/auto-categorize
Authorization: Bearer <token>
X-Space: my-space
```

**Response:**
```json
{
  "data": {
    "queued": true,
    "content_id": "01hd..."
  }
}
```

The job uses `TaxonomyCategorizationService` to analyze the content body, score each available term, and assign those above the confidence threshold (default: `0.7`).

---

## Admin Guide

The taxonomy manager is available at **Admin → Taxonomy** (`/admin/taxonomy`).

### Managing Vocabularies

**The Vocabulary List** (`/admin/taxonomy`) shows all vocabularies for the default space with their term counts. From here you can:

- **Create** a new vocabulary via the form panel
- **Click** a vocabulary name to open its term tree editor
- **Edit** or **Delete** a vocabulary inline

When creating a vocabulary:
- **Name** is required; slug is auto-generated but can be customized
- **Hierarchy** controls whether terms can be nested (enable for categories, disable for flat tag clouds)
- **Allow Multiple** controls whether content can have more than one term from this vocabulary
- **Sort Order** controls the display order in the vocabulary list and the content editor's term picker

### Managing Terms

**The Term Tree Editor** (`/admin/taxonomy/{id}`) shows a drag-and-drop tree for one vocabulary.

#### Adding Terms

1. Click **+ Add Term** (or the **+** button next to a parent term to create a child)
2. Fill in the name; slug is auto-generated
3. Optionally add a description and metadata (JSON)
4. Click **Save**

#### Organizing the Tree

- **Drag** a term node to reorder it among its siblings
- **Drag** a term onto another term to reparent it
- Changes are saved immediately via AJAX — no page reload needed

#### Deleting Terms

Click the **×** button on a term. You'll be prompted to choose:
- **Reparent children** — child terms move up to the deleted term's parent (default, safe)
- **Delete subtree** — the term and all its descendants are permanently deleted

### Tagging Content

From the **Content Edit** page, the sidebar contains a **Terms** panel (powered by `TermPicker.vue`):

- Terms are grouped by vocabulary
- Use the search box to filter terms by name
- Click a term badge to assign it; click again to remove
- AI-suggested terms appear with a **confidence badge** — click to accept or ignore them

---

## Developer Guide

### Models & Relationships

#### `Vocabulary`

```php
use App\Models\Vocabulary;

// Find a vocabulary for a space
$vocabulary = Vocabulary::forSpace($spaceId)
    ->where('slug', 'categories')
    ->firstOrFail();

// Get root terms only
$rootTerms = $vocabulary->rootTerms;  // ordered by sort_order

// Get all terms
$allTerms = $vocabulary->terms;
```

**Scopes:**

| Scope | Usage |
|-------|-------|
| `forSpace($spaceId)` | Filter by space |
| `ordered()` | Order by `sort_order` |

---

#### `TaxonomyTerm`

```php
use App\Models\TaxonomyTerm;

// Get the full tree for a vocabulary
$roots = TaxonomyTerm::inVocabulary($vocabularyId)
    ->roots()
    ->ordered()
    ->with('childrenRecursive')
    ->get();

// Find all descendants of a term
$descendants = TaxonomyTerm::descendantsOf($termId)->get();

// Get ancestor IDs from the materialized path
$ancestorIds = $term->getAncestorIds(); // array of ULID strings

// Check ancestry
$isParent = $termA->isAncestorOf($termB); // bool
```

**Scopes:**

| Scope | Usage |
|-------|-------|
| `roots()` | Only root terms (no parent) |
| `ordered()` | Order by `sort_order`, then `name` |
| `inVocabulary($vocabularyId)` | Filter by vocabulary |
| `descendantsOf($termId)` | All descendants via materialized path |

**Relations:**

| Relation | Type | Description |
|----------|------|-------------|
| `vocabulary()` | BelongsTo | Parent vocabulary |
| `parent()` | BelongsTo | Parent term (nullable) |
| `children()` | HasMany | Direct child terms |
| `childrenRecursive()` | HasMany | All descendants (eager-load for trees) |
| `contents()` | BelongsToMany | Content items via `content_taxonomy` |

**Path computation** is automatic. When a term is created or its `parent_id` changes, the model recalculates `path` (materialized path like `/root-id/parent-id/this-id`) and `depth` (integer, 0 = root).

---

#### `Content` (additions)

The `Content` model has two new relationships and two new scopes:

```php
// Get all taxonomy terms for a content item
$terms = $content->taxonomyTerms; // BelongsToMany with pivot data

// Get terms for a specific vocabulary
$categories = $content->termsInVocabulary('categories')->get();

// Query: filter content by term ID
$laravelContent = Content::inTerm($termId)->published()->get();

// Query: filter content by vocab slug + term slug
$content = Content::inTaxonomy('categories', 'laravel')->published()->get();
```

**Pivot columns available:**
```php
$content->taxonomyTerms->each(function ($term) {
    $term->pivot->sort_order;   // int
    $term->pivot->auto_assigned; // bool
    $term->pivot->confidence;    // float|null
});
```

---

### Service Layer

All taxonomy operations go through `App\Services\Taxonomy\TaxonomyService`. Inject it via the constructor or resolve from the container.

```php
use App\Services\Taxonomy\TaxonomyService;

class MyController extends Controller
{
    public function __construct(private readonly TaxonomyService $taxonomy) {}
}
```

#### Creating a Vocabulary

```php
$vocabulary = $this->taxonomy->createVocabulary($spaceId, [
    'name' => 'Topics',
    'hierarchy' => true,
    'allow_multiple' => true,
]);
```

#### Creating a Term

```php
$term = $this->taxonomy->createTerm($vocabulary->id, [
    'name'      => 'Laravel',
    'parent_id' => $webDevTerm->id,  // optional
]);
```

Slug is auto-generated from name if not provided. Uniqueness is enforced per vocabulary.

#### Moving a Term

```php
// Move under a new parent
$term = $this->taxonomy->moveTerm($term, $newParentId);

// Promote to root
$term = $this->taxonomy->moveTerm($term, null);
```

Paths for the entire subtree are recomputed atomically.

#### Reordering Terms

```php
$this->taxonomy->reorderTerms([
    $termIdA => 0,
    $termIdB => 1,
    $termIdC => 2,
]);
```

#### Assigning Terms to Content

```php
// Additive — does not remove existing assignments
$this->taxonomy->assignTerms($content, [
    ['term_id' => $termId, 'sort_order' => 0],
    ['term_id' => $termId2, 'sort_order' => 1, 'auto_assigned' => true, 'confidence' => 0.92],
]);

// Sync — replaces all assignments
$this->taxonomy->syncTerms($content, [$termId, $termId2]);

// Remove specific terms
$this->taxonomy->removeTerms($content, [$termId]);
```

`content_count` on all affected terms is automatically recalculated after assignment changes.

#### Getting Content for a Term

```php
// Published content for this term only
$paginator = $this->taxonomy->getContentForTerm($term, perPage: 20);

// Include content tagged with any descendant term
$paginator = $this->taxonomy->getContentForTerm($term, includeDescendants: true, perPage: 20);
```

#### Building a Tree

```php
$tree = $this->taxonomy->getTree($vocabularyId);
// Collection of root TaxonomyTerm models, each with ->childrenRecursive loaded
```

---

### AI Auto-Categorization

`App\Services\Taxonomy\TaxonomyCategorizationService` uses the configured LLM to analyze content and suggest terms.

```php
use App\Services\Taxonomy\TaxonomyCategorizationService;

// Suggest terms (no side effects)
$suggestions = $service->suggestTerms($content);
// [['term' => TaxonomyTerm, 'confidence' => 0.95], ...]

// Limit to one vocabulary
$suggestions = $service->suggestTerms($content, vocabularyId: $vocabId);

// Auto-assign (persists to DB, fires assignTerms)
$assigned = $service->autoAssign(
    content: $content,
    confidenceThreshold: 0.7,   // default
    vocabularyId: null,          // null = all vocabularies
);
```

**Configuration** in `config/numen.php`:

```php
'taxonomy' => [
    'auto_assign_threshold'  => env('NUMEN_TAXONOMY_AUTO_ASSIGN_THRESHOLD', 0.7),
    'auto_assign_max_terms'  => env('NUMEN_TAXONOMY_AUTO_ASSIGN_MAX', 5),
    'categorization_model'   => env('NUMEN_TAXONOMY_MODEL', 'claude-haiku-4-5-20251001'),
    'categorization_provider'=> env('NUMEN_TAXONOMY_PROVIDER', 'anthropic'),
    'max_depth'              => env('NUMEN_TAXONOMY_MAX_DEPTH', 10),
],
```

**Environment variables** to add to `.env`:

```dotenv
NUMEN_TAXONOMY_AUTO_ASSIGN_THRESHOLD=0.7
NUMEN_TAXONOMY_AUTO_ASSIGN_MAX=5
NUMEN_TAXONOMY_MODEL=claude-haiku-4-5-20251001
NUMEN_TAXONOMY_PROVIDER=anthropic
NUMEN_TAXONOMY_MAX_DEPTH=10
```

---

### Pipeline Integration

The `CategorizePipelineContent` job can be dispatched as part of a content pipeline run.

```php
use App\Jobs\CategorizePipelineContent;

// Dispatch for a specific content item
CategorizePipelineContent::dispatch($content);
```

To add auto-categorization as a pipeline stage, configure the pipeline:

```json
{
  "stages": [
    { "name": "content_creation",  "agent": "content_creator" },
    { "name": "seo_optimization",  "agent": "seo_specialist" },
    { "name": "auto_categorize",   "agent": "taxonomy_categorizer" },
    { "name": "editorial_review",  "agent": "editor" },
    { "name": "publish",           "agent": "publisher" }
  ]
}
```

The categorization stage logs its results to `pipeline_runs.context` under `taxonomy_auto_assigned`:

```json
{
  "taxonomy_auto_assigned": [
    { "term_id": "01ha...", "term_name": "Laravel", "confidence": 0.95 },
    { "term_id": "01hb...", "term_name": "PHP",     "confidence": 0.88 }
  ]
}
```

---

### Extending Taxonomy

#### Custom Vocabulary Settings

The `settings` JSON column on vocabularies is reserved for extension. Use it to store content-type restrictions, display preferences, or validation rules:

```php
$vocabulary = $this->taxonomy->createVocabulary($spaceId, [
    'name' => 'Product Tags',
    'settings' => [
        'content_type_restrictions' => ['product', 'product-review'],
        'required'  => true,
        'min_terms' => 1,
        'max_terms' => 10,
    ],
]);
```

#### Custom Term Metadata

The `metadata` JSON column on terms is unstructured — store whatever you need:

```php
$term = $this->taxonomy->createTerm($vocab->id, [
    'name'     => 'Laravel',
    'metadata' => [
        'color'    => '#FF2D20',
        'icon'     => 'laravel',
        'seo_title'  => 'Laravel Tutorials & Guides',
        'seo_description' => 'Browse our Laravel content library.',
    ],
]);
```

#### Creating a Programmatic Seeder

```php
<?php

namespace Database\Seeders;

use App\Models\Space;
use App\Services\Taxonomy\TaxonomyService;
use Illuminate\Database\Seeder;

class MyTaxonomySeeder extends Seeder
{
    public function __construct(private readonly TaxonomyService $taxonomy) {}

    public function run(): void
    {
        $space = Space::where('slug', 'default')->firstOrFail();

        $vocab = $this->taxonomy->createVocabulary($space->id, [
            'name'      => 'Categories',
            'hierarchy' => true,
        ]);

        $tech = $this->taxonomy->createTerm($vocab->id, ['name' => 'Technology']);
        $web  = $this->taxonomy->createTerm($vocab->id, ['name' => 'Web Development', 'parent_id' => $tech->id]);
        $this->taxonomy->createTerm($vocab->id, ['name' => 'Laravel', 'parent_id' => $web->id]);
        $this->taxonomy->createTerm($vocab->id, ['name' => 'Vue.js',  'parent_id' => $web->id]);
    }
}
```

#### Filtering Content by Taxonomy in the API

The content delivery API supports taxonomy filtering via query parameters:

```http
GET /api/v1/content?taxonomy[categories]=laravel,php
GET /api/v1/content?taxonomy[tags]=tutorial
```

You can also use the Eloquent scopes directly:

```php
// Single term
Content::inTerm($termId)->published()->paginate(20);

// By vocab + term slug
Content::inTaxonomy('categories', 'laravel')->published()->paginate(20);
```

---

## Migration Guide

### What's New (2026-03-07)

Three new tables were introduced. **No existing tables were modified.**

#### New tables

| Table | Purpose |
|-------|---------|
| `vocabularies` | Vocabulary definitions, scoped to spaces |
| `taxonomy_terms` | Term definitions with hierarchical adjacency list + materialized path |
| `content_taxonomy` | Pivot table linking content to terms |

#### Running the Migrations

```bash
php artisan migrate
```

If you are on a fresh install, this runs automatically. For existing installations:

```bash
php artisan migrate --step
```

The migration order matters — `vocabularies` must run before `taxonomy_terms`, and `taxonomy_terms` before `content_taxonomy`.

#### Migration History

| Migration | What it does |
|-----------|-------------|
| `2026_03_07_000001_create_vocabularies_table` | Creates `vocabularies` with ULID PK, space FK, slug unique constraint |
| `2026_03_07_000002_create_taxonomy_terms_table` | Creates `taxonomy_terms` with self-referencing `parent_id`, materialized `path`, depth |
| `2026_03_07_000003_create_content_taxonomy_table` | Creates pivot table (initial version with separate ULID PK) |
| `2026_03_07_080100_add_updated_at_to_content_taxonomy_table` | Adds `updated_at` column to pivot |
| `2026_03_07_080533_fix_content_taxonomy_primary_key` | **Recreates** pivot table with composite PK `(content_id, term_id)` — required for Laravel's `BelongsToMany::sync()` and `attach()` |

> ⚠️ **The `fix_content_taxonomy_primary_key` migration drops and recreates `content_taxonomy`.** On a fresh install this is a no-op. On an existing installation with taxonomy data, this migration will clear the pivot table. If you have existing `content_taxonomy` rows (from a pre-release build), back them up before migrating.

#### Rolling Back

```bash
# Roll back all taxonomy migrations
php artisan migrate:rollback --step=5
```

Rollback cascades correctly — `content_taxonomy` is dropped first, then `taxonomy_terms`, then `vocabularies`.

#### Schema Details

**`vocabularies`**

```sql
id           CHAR(26) PRIMARY KEY    -- ULID
space_id     CHAR(26) NOT NULL       -- FK → spaces.id (cascade delete)
name         VARCHAR(255) NOT NULL
slug         VARCHAR(255) NOT NULL
description  TEXT NULL
hierarchy    TINYINT(1) DEFAULT 1
allow_multiple TINYINT(1) DEFAULT 1
settings     JSON NULL
sort_order   INT DEFAULT 0
created_at   TIMESTAMP NULL
updated_at   TIMESTAMP NULL

UNIQUE KEY (space_id, slug)
INDEX (space_id)
```

**`taxonomy_terms`**

```sql
id             CHAR(26) PRIMARY KEY   -- ULID
vocabulary_id  CHAR(26) NOT NULL      -- FK → vocabularies.id (cascade delete)
parent_id      CHAR(26) NULL          -- FK → taxonomy_terms.id (set null on delete)
name           VARCHAR(255) NOT NULL
slug           VARCHAR(255) NOT NULL
description    TEXT NULL
path           VARCHAR(1000) NULL     -- Materialized: "/root-id/parent-id/this-id"
depth          INT DEFAULT 0
sort_order     INT DEFAULT 0
metadata       JSON NULL
content_count  INT DEFAULT 0          -- Denormalized, auto-refreshed on assignment changes
created_at     TIMESTAMP NULL
updated_at     TIMESTAMP NULL

UNIQUE KEY (vocabulary_id, slug)
INDEX (vocabulary_id, parent_id)
INDEX (path)
INDEX (sort_order)
```

**`content_taxonomy`** (pivot)

```sql
content_id    CHAR(26) NOT NULL    -- FK → contents.id (cascade delete)
term_id       CHAR(26) NOT NULL    -- FK → taxonomy_terms.id (cascade delete)
sort_order    INT DEFAULT 0
auto_assigned TINYINT(1) DEFAULT 0
confidence    DECIMAL(5,4) NULL    -- 0.0000–1.0000
created_at    TIMESTAMP NULL
updated_at    TIMESTAMP NULL

PRIMARY KEY (content_id, term_id)
INDEX (content_id)
INDEX (term_id)
```

### Legacy `taxonomy` JSON Column

Prior to this feature, the `Content` model had a JSON column named `taxonomy` for unstructured classification data. That column is **not removed** — it remains for backward compatibility. 

**You should migrate custom data from `contents.taxonomy` to the new relational system.** A one-off Artisan command is a good pattern:

```php
// Example migration script (not included — write one specific to your data)
Content::whereNotNull('taxonomy')->chunk(100, function ($items) use ($service) {
    foreach ($items as $content) {
        // Map your JSON structure to term assignments
    }
});
```

The `taxonomy` JSON column is **deprecated** and will be removed in a future version.

---

*Documentation by Scribe 📝 — Numen Technical Writing*
