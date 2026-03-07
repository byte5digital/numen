# Taxonomy & Content Organization — Architecture Plan

**Author:** Blueprint 🏗️ (Numen Software Architect)
**Date:** 2026-03-07
**Status:** Proposed
**Discussion:** GitHub Discussion #8

---

## 1. Overview

Numen needs a flexible, hierarchical taxonomy system that allows content to be organized across multiple vocabularies (e.g., Categories, Tags, Topics). The system must support:

- Multiple vocabulary types per space
- Hierarchical terms (nested tree via `parent_id`)
- Many-to-many content↔term relationships
- SEO-friendly slugs and term listing pages
- AI-driven auto-categorization during pipeline execution
- Drag-and-drop admin UI for tree management

### Design Principles

1. **Space-scoped** — vocabularies and terms belong to a space, consistent with all other Numen entities
2. **Polymorphic-ready** — the pivot table uses a morphable pattern so future entities (Pages, MediaAssets) can also be tagged
3. **Adjacency list** — `parent_id` for hierarchy (simple, well-understood, works with Laravel's recursive eager-loading; materialized path added for fast ancestor queries)
4. **No existing migration modifications** — all schema changes via new migrations

---

## 2. Database Schema

### 2.1 New Tables

#### `vocabularies`

| Column | Type | Notes |
|--------|------|-------|
| `id` | `ulid` PK | |
| `space_id` | `ulid` FK → spaces | |
| `name` | `string(255)` | Human-readable: "Categories", "Tags" |
| `slug` | `string(255)` | URL-friendly: "categories", "tags" |
| `description` | `text` nullable | Optional description |
| `hierarchy` | `boolean` default `true` | Whether terms can be nested |
| `allow_multiple` | `boolean` default `true` | Whether content can have multiple terms |
| `settings` | `json` nullable | Future extensibility (e.g., max depth, required) |
| `sort_order` | `integer` default `0` | Ordering within a space |
| `created_at` | `timestamp` | |
| `updated_at` | `timestamp` | |

**Indexes:** `unique(space_id, slug)`, `index(space_id)`

#### `taxonomy_terms`

| Column | Type | Notes |
|--------|------|-------|
| `id` | `ulid` PK | |
| `vocabulary_id` | `ulid` FK → vocabularies | |
| `parent_id` | `ulid` FK → taxonomy_terms, nullable | Self-referencing for hierarchy |
| `name` | `string(255)` | "Laravel", "PHP", "Getting Started" |
| `slug` | `string(255)` | "laravel", "php", "getting-started" |
| `description` | `text` nullable | Term description (SEO meta) |
| `path` | `string(1000)` nullable | Materialized path: "/root-id/parent-id/this-id" |
| `depth` | `integer` default `0` | Nesting depth (0 = root) |
| `sort_order` | `integer` default `0` | Sibling sort order |
| `metadata` | `json` nullable | Custom data (icon, color, image, SEO overrides) |
| `content_count` | `integer` default `0` | Cached count (denormalized for performance) |
| `created_at` | `timestamp` | |
| `updated_at` | `timestamp` | |

**Indexes:** `unique(vocabulary_id, slug)`, `index(vocabulary_id, parent_id)`, `index(path)`, `index(sort_order)`

#### `content_taxonomy` (pivot)

| Column | Type | Notes |
|--------|------|-------|
| `id` | `ulid` PK | |
| `content_id` | `ulid` FK → contents | |
| `term_id` | `ulid` FK → taxonomy_terms | |
| `sort_order` | `integer` default `0` | Ordering within content's terms |
| `auto_assigned` | `boolean` default `false` | True if AI-assigned |
| `confidence` | `decimal(5,4)` nullable | AI confidence score (0.0000–1.0000) |
| `created_at` | `timestamp` | |

**Indexes:** `unique(content_id, term_id)`, `index(term_id)`, `index(content_id)`

### 2.2 Migration Files

All migrations are NEW files — no existing migrations are touched.

```
database/migrations/2026_03_07_000001_create_vocabularies_table.php
database/migrations/2026_03_07_000002_create_taxonomy_terms_table.php
database/migrations/2026_03_07_000003_create_content_taxonomy_table.php
```

#### Migration 1: `create_vocabularies_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vocabularies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('space_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('hierarchy')->default(true);
            $table->boolean('allow_multiple')->default(true);
            $table->json('settings')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['space_id', 'slug']);
            $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vocabularies');
    }
};
```

#### Migration 2: `create_taxonomy_terms_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_terms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('vocabulary_id')->index();
            $table->ulid('parent_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('path', 1000)->nullable()->index();
            $table->integer('depth')->default(0);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->integer('content_count')->default(0);
            $table->timestamps();

            $table->unique(['vocabulary_id', 'slug']);
            $table->index(['vocabulary_id', 'parent_id']);
            $table->foreign('vocabulary_id')->references('id')->on('vocabularies')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('taxonomy_terms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_terms');
    }
};
```

#### Migration 3: `create_content_taxonomy_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_taxonomy', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('content_id');
            $table->ulid('term_id');
            $table->integer('sort_order')->default(0);
            $table->boolean('auto_assigned')->default(false);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['content_id', 'term_id']);
            $table->index('term_id');
            $table->index('content_id');
            $table->foreign('content_id')->references('id')->on('contents')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('taxonomy_terms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_taxonomy');
    }
};
```

---

## 3. Models & Relationships

### 3.1 New Models

#### `App\Models\Vocabulary`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vocabulary extends Model
{
    use HasUlids;

    protected $fillable = [
        'space_id', 'name', 'slug', 'description',
        'hierarchy', 'allow_multiple', 'settings', 'sort_order',
    ];

    protected $casts = [
        'hierarchy' => 'boolean',
        'allow_multiple' => 'boolean',
        'settings' => 'array',
    ];

    // --- Scopes ---

    public function scopeForSpace($query, string $spaceId)
    {
        return $query->where('space_id', $spaceId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // --- Relations ---

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class);
    }

    public function rootTerms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class)->whereNull('parent_id')->orderBy('sort_order');
    }
}
```

#### `App\Models\TaxonomyTerm`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TaxonomyTerm extends Model
{
    use HasUlids;

    protected $fillable = [
        'vocabulary_id', 'parent_id', 'name', 'slug',
        'description', 'path', 'depth', 'sort_order',
        'metadata', 'content_count',
    ];

    protected $casts = [
        'metadata' => 'array',
        'depth' => 'integer',
        'sort_order' => 'integer',
        'content_count' => 'integer',
    ];

    // --- Boot ---

    protected static function booted(): void
    {
        static::creating(function (TaxonomyTerm $term) {
            if (empty($term->slug)) {
                $term->slug = Str::slug($term->name);
            }
            $term->computePath();
        });

        static::updating(function (TaxonomyTerm $term) {
            if ($term->isDirty('parent_id')) {
                $term->computePath();
            }
        });
    }

    // --- Scopes ---

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeInVocabulary($query, string $vocabularyId)
    {
        return $query->where('vocabulary_id', $vocabularyId);
    }

    public function scopeDescendantsOf($query, string $termId)
    {
        return $query->where('path', 'like', "%/{$termId}/%");
    }

    // --- Relations ---

    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(Vocabulary::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class, 'parent_id')->orderBy('sort_order');
    }

    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'content_taxonomy', 'term_id', 'content_id')
            ->withPivot('sort_order', 'auto_assigned', 'confidence')
            ->withTimestamps();
    }

    // Recursive eager-load for tree building
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    // --- Helpers ---

    public function computePath(): void
    {
        if ($this->parent_id && $this->parent) {
            $this->path = $this->parent->path . '/' . $this->id;
            $this->depth = $this->parent->depth + 1;
        } else {
            $this->path = '/' . ($this->id ?? '');
            $this->depth = 0;
        }
    }

    public function getAncestorIds(): array
    {
        return array_filter(explode('/', $this->path ?? ''));
    }

    public function isAncestorOf(TaxonomyTerm $other): bool
    {
        return str_contains($other->path ?? '', '/' . $this->id . '/');
    }

    public function incrementContentCount(): void
    {
        $this->increment('content_count');
    }

    public function decrementContentCount(): void
    {
        $this->decrement('content_count');
    }
}
```

### 3.2 Updated Existing Models

#### `Content` — add `taxonomyTerms()` relationship

```php
// Add to Content model:

public function taxonomyTerms(): BelongsToMany
{
    return $this->belongsToMany(TaxonomyTerm::class, 'content_taxonomy', 'content_id', 'term_id')
        ->withPivot('sort_order', 'auto_assigned', 'confidence')
        ->withTimestamps();
}

public function termsInVocabulary(string $vocabularySlug): BelongsToMany
{
    return $this->taxonomyTerms()
        ->whereHas('vocabulary', fn ($q) => $q->where('slug', $vocabularySlug));
}

// Scope: filter content by term
public function scopeInTerm($query, string $termId)
{
    return $query->whereHas('taxonomyTerms', fn ($q) => $q->where('taxonomy_terms.id', $termId));
}

// Scope: filter content by vocabulary slug + term slug
public function scopeInTaxonomy($query, string $vocabSlug, string $termSlug)
{
    return $query->whereHas('taxonomyTerms', function ($q) use ($vocabSlug, $termSlug) {
        $q->where('taxonomy_terms.slug', $termSlug)
          ->whereHas('vocabulary', fn ($v) => $v->where('slug', $vocabSlug));
    });
}
```

#### `Space` — add `vocabularies()` relationship

```php
// Add to Space model:

public function vocabularies(): HasMany
{
    return $this->hasMany(Vocabulary::class)->orderBy('sort_order');
}
```

---

## 4. Service Layer

### 4.1 `App\Services\Taxonomy\TaxonomyService`

Core service for taxonomy operations. Keeps controllers thin.

```php
<?php

namespace App\Services\Taxonomy;

use App\Models\Content;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TaxonomyService
{
    /**
     * Create a vocabulary within a space.
     */
    public function createVocabulary(string $spaceId, array $data): Vocabulary;

    /**
     * Create a term within a vocabulary (handles parent_id, path computation, slug generation).
     */
    public function createTerm(string $vocabularyId, array $data): TaxonomyTerm;

    /**
     * Move a term to a new parent (re-compute path for term and all descendants).
     */
    public function moveTerm(TaxonomyTerm $term, ?string $newParentId): TaxonomyTerm;

    /**
     * Reorder siblings within a parent.
     * @param array<string, int> $ordering  [term_id => sort_order]
     */
    public function reorderTerms(array $ordering): void;

    /**
     * Build a full tree structure for a vocabulary.
     * @return Collection<int, TaxonomyTerm>  Root terms with nested childrenRecursive
     */
    public function getTree(string $vocabularyId): Collection;

    /**
     * Assign terms to content (with optional AI metadata).
     * @param array<int, array{term_id: string, auto_assigned?: bool, confidence?: float}> $assignments
     */
    public function assignTerms(Content $content, array $assignments): void;

    /**
     * Remove term assignments from content.
     * @param array<int, string> $termIds
     */
    public function removeTerms(Content $content, array $termIds): void;

    /**
     * Sync content's terms (replaces all assignments).
     * @param array<int, string> $termIds
     */
    public function syncTerms(Content $content, array $termIds): void;

    /**
     * Get all content for a term (including descendants if $includeDescendants = true).
     */
    public function getContentForTerm(
        TaxonomyTerm $term,
        bool $includeDescendants = false,
        int $perPage = 20
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    /**
     * Recalculate content_count for a term (and optionally ancestors).
     */
    public function recalculateContentCount(TaxonomyTerm $term): void;

    /**
     * Delete a term (optionally reassign children to parent or delete subtree).
     */
    public function deleteTerm(TaxonomyTerm $term, string $childStrategy = 'reparent'): void;

    /**
     * Generate a unique slug within a vocabulary.
     */
    public function generateUniqueSlug(string $vocabularyId, string $name, ?string $excludeId = null): string;
}
```

### 4.2 `App\Services\Taxonomy\TaxonomyCategorizationService`

AI-powered auto-categorization — called during pipeline execution.

```php
<?php

namespace App\Services\Taxonomy;

use App\Models\Content;
use App\Models\TaxonomyTerm;
use App\Services\AI\LLMManager;

class TaxonomyCategorizationService
{
    public function __construct(
        private LLMManager $llm,
        private TaxonomyService $taxonomy,
    ) {}

    /**
     * Analyze content and suggest taxonomy terms.
     * Returns term suggestions with confidence scores.
     *
     * @return array<int, array{term: TaxonomyTerm, confidence: float}>
     */
    public function suggestTerms(Content $content, ?string $vocabularyId = null): array;

    /**
     * Auto-assign terms to content based on AI analysis.
     * Only assigns terms above the confidence threshold.
     */
    public function autoAssign(
        Content $content,
        float $confidenceThreshold = 0.7,
        ?string $vocabularyId = null
    ): array;

    /**
     * Build the LLM prompt for categorization.
     * Includes the vocabulary structure + content body.
     */
    private function buildCategorizationPrompt(Content $content, array $availableTerms): string;
}
```

### 4.3 Pipeline Integration

A new pipeline stage for auto-categorization:

```php
<?php

namespace App\Jobs;

use App\Models\PipelineRun;
use App\Services\Taxonomy\TaxonomyCategorizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CategorizePipelineContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public PipelineRun $run,
    ) {}

    public function handle(TaxonomyCategorizationService $service): void
    {
        $content = $this->run->content;

        $assignments = $service->autoAssign(
            content: $content,
            confidenceThreshold: config('numen.taxonomy.auto_assign_threshold', 0.7),
        );

        // Log AI categorization
        $this->run->update([
            'context' => array_merge($this->run->context ?? [], [
                'taxonomy_auto_assigned' => collect($assignments)->map(fn ($a) => [
                    'term_id' => $a['term']->id,
                    'term_name' => $a['term']->name,
                    'confidence' => $a['confidence'],
                ])->toArray(),
            ]),
        ]);
    }
}
```

---

## 5. API Endpoints

All endpoints under `/api/v1/` following existing conventions.

### 5.1 Public (Read-Only) — Content Delivery

```
GET  /api/v1/taxonomies                          → List vocabularies for a space
GET  /api/v1/taxonomies/{vocabSlug}               → Show vocabulary with root terms
GET  /api/v1/taxonomies/{vocabSlug}/terms          → Flat list of terms (with ?tree=1 for nested)
GET  /api/v1/taxonomies/{vocabSlug}/terms/{termSlug}  → Show term details
GET  /api/v1/taxonomies/{vocabSlug}/terms/{termSlug}/content  → Paginated content listing for term
GET  /api/v1/content/{slug}/terms                  → List all terms for a piece of content
```

### 5.2 Authenticated (Management) — Admin API

```
POST   /api/v1/taxonomies                         → Create vocabulary
PUT    /api/v1/taxonomies/{id}                     → Update vocabulary
DELETE /api/v1/taxonomies/{id}                     → Delete vocabulary

POST   /api/v1/taxonomies/{vocabId}/terms          → Create term
PUT    /api/v1/terms/{id}                          → Update term
DELETE /api/v1/terms/{id}                          → Delete term
POST   /api/v1/terms/{id}/move                     → Move term (change parent)
POST   /api/v1/terms/reorder                       → Batch reorder siblings

POST   /api/v1/content/{id}/terms                  → Assign terms to content
PUT    /api/v1/content/{id}/terms                  → Sync (replace) terms on content
DELETE /api/v1/content/{id}/terms/{termId}          → Remove term from content

POST   /api/v1/content/{id}/auto-categorize         → Trigger AI categorization
```

### 5.3 Route Definitions

```php
// routes/api.php — add within the v1 prefix group

// Public taxonomy routes
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/taxonomies', [TaxonomyController::class, 'index']);
    Route::get('/taxonomies/{vocabSlug}', [TaxonomyController::class, 'show']);
    Route::get('/taxonomies/{vocabSlug}/terms', [TaxonomyTermController::class, 'index']);
    Route::get('/taxonomies/{vocabSlug}/terms/{termSlug}', [TaxonomyTermController::class, 'show']);
    Route::get('/taxonomies/{vocabSlug}/terms/{termSlug}/content', [TaxonomyTermController::class, 'content']);
    Route::get('/content/{slug}/terms', [ContentController::class, 'terms']);
});

// Authenticated taxonomy management
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/taxonomies', [TaxonomyController::class, 'store']);
    Route::put('/taxonomies/{id}', [TaxonomyController::class, 'update']);
    Route::delete('/taxonomies/{id}', [TaxonomyController::class, 'destroy']);

    Route::post('/taxonomies/{vocabId}/terms', [TaxonomyTermController::class, 'store']);
    Route::put('/terms/{id}', [TaxonomyTermController::class, 'update']);
    Route::delete('/terms/{id}', [TaxonomyTermController::class, 'destroy']);
    Route::post('/terms/{id}/move', [TaxonomyTermController::class, 'move']);
    Route::post('/terms/reorder', [TaxonomyTermController::class, 'reorder']);

    Route::post('/content/{id}/terms', [ContentTaxonomyController::class, 'assign']);
    Route::put('/content/{id}/terms', [ContentTaxonomyController::class, 'sync']);
    Route::delete('/content/{id}/terms/{termId}', [ContentTaxonomyController::class, 'remove']);

    Route::post('/content/{id}/auto-categorize', [ContentTaxonomyController::class, 'autoCategorize']);
});
```

### 5.4 Controllers

```
app/Http/Controllers/Api/TaxonomyController.php         — Vocabulary CRUD
app/Http/Controllers/Api/TaxonomyTermController.php      — Term CRUD + tree ops
app/Http/Controllers/Api/ContentTaxonomyController.php   — Content↔Term assignment
```

### 5.5 API Resources (JSON transformation)

```
app/Http/Resources/VocabularyResource.php
app/Http/Resources/TaxonomyTermResource.php
app/Http/Resources/TaxonomyTermTreeResource.php   — recursive tree serialization
```

---

## 6. Admin UI Components (Vue 3 / Inertia.js)

### 6.1 New Pages

```
resources/js/Pages/Taxonomy/Index.vue            — List all vocabularies
resources/js/Pages/Taxonomy/Show.vue             — View/manage terms in a vocabulary (tree editor)
resources/js/Pages/Taxonomy/VocabularyForm.vue    — Create/edit vocabulary modal/form
```

### 6.2 New Components

```
resources/js/Components/Taxonomy/TermTree.vue        — Recursive drag-and-drop tree (core component)
resources/js/Components/Taxonomy/TermTreeNode.vue     — Single tree node (name, actions, drag handle)
resources/js/Components/Taxonomy/TermForm.vue         — Create/edit term form (inline or modal)
resources/js/Components/Taxonomy/TermPicker.vue       — Multi-select term picker for content editor
resources/js/Components/Taxonomy/TermBadge.vue        — Small badge showing a term (with remove action)
resources/js/Components/Taxonomy/TermBreadcrumb.vue   — Ancestry breadcrumb for a term
```

### 6.3 Integration with Existing UI

#### Content Editor Enhancement

The `TermPicker.vue` component integrates into the existing content edit form:

```
resources/js/Pages/Content/Edit.vue
  └── TermPicker (sidebar panel)
       ├── Grouped by vocabulary
       ├── Searchable typeahead
       ├── Shows AI-suggested terms with confidence badges
       └── Toggle per vocabulary (tree select vs. tag input)
```

#### Dashboard Widget

```
resources/js/Components/Dashboard/TaxonomyOverview.vue  — Term counts, recent assignments
```

### 6.4 Drag-and-Drop Implementation

Use **[vuedraggable](https://github.com/SortableJS/vue.draggable.next)** (Vue 3 wrapper for SortableJS):

- `TermTree.vue` renders a recursive `<draggable>` list
- On drag-end: emit `move` event → calls `POST /terms/{id}/move` and `POST /terms/reorder`
- Optimistic UI update with rollback on API failure

---

## 7. Integration Points

### 7.1 Content Model Integration

The existing `Content.taxonomy` JSON column is currently a simple JSON field. With the new system:

- **Migration path:** The JSON `taxonomy` field remains for backward compatibility during transition
- **New system:** Uses the `content_taxonomy` pivot table for structured relationships
- **Deprecation:** The `taxonomy` JSON column should be marked `@deprecated` and eventually removed in a future migration

### 7.2 ContentType Integration

Vocabularies can be linked to ContentTypes via a configuration in the vocabulary's `settings`:

```json
{
  "content_type_restrictions": ["blog-post", "article"],
  "required": true,
  "min_terms": 1,
  "max_terms": 5
}
```

This allows certain vocabularies to only apply to specific content types and to enforce minimum/maximum term counts during validation.

### 7.3 Pipeline Integration

The `PipelineExecutor` should be extended to optionally include an `auto_categorize` stage:

```php
// In ContentPipeline stage configuration:
{
    "stages": [
        {"name": "content_creation", "agent": "content_creator"},
        {"name": "seo_optimization", "agent": "seo_specialist"},
        {"name": "auto_categorize", "agent": "taxonomy_categorizer"},  // NEW
        {"name": "editorial_review", "agent": "editor"},
        {"name": "publish", "agent": "publisher"}
    ]
}
```

The `auto_categorize` stage:
1. Reads the generated content from the pipeline context
2. Fetches all active vocabularies for the space
3. Calls `TaxonomyCategorizationService::autoAssign()`
4. Stores assignments in `content_taxonomy` with `auto_assigned = true`
5. Optionally pauses for human review if configured

### 7.4 Space Scoping

All taxonomy queries are scoped to the current space, consistent with existing patterns:

```php
// In controllers:
$space = Space::where('slug', $request->header('X-Space') ?? 'default')->firstOrFail();
$vocabularies = $space->vocabularies()->ordered()->get();
```

### 7.5 Content Delivery API Enhancement

The existing `GET /api/v1/content` and `GET /api/v1/content/{slug}` endpoints should be enhanced:

```php
// GET /api/v1/content?taxonomy[categories]=laravel,php
// GET /api/v1/content?taxonomy[tags]=tutorial

// In ContentController::index() — add taxonomy filtering
if ($request->has('taxonomy')) {
    foreach ($request->input('taxonomy') as $vocabSlug => $termSlugs) {
        $termSlugs = explode(',', $termSlugs);
        $query->whereHas('taxonomyTerms', function ($q) use ($vocabSlug, $termSlugs) {
            $q->whereIn('taxonomy_terms.slug', $termSlugs)
              ->whereHas('vocabulary', fn ($v) => $v->where('slug', $vocabSlug));
        });
    }
}

// In content show response — include terms
'terms' => TaxonomyTermResource::collection($content->taxonomyTerms),
```

---

## 8. Configuration

Add to `config/numen.php`:

```php
'taxonomy' => [
    // AI auto-categorization settings
    'auto_assign_threshold' => env('NUMEN_TAXONOMY_AUTO_ASSIGN_THRESHOLD', 0.7),
    'auto_assign_max_terms' => env('NUMEN_TAXONOMY_AUTO_ASSIGN_MAX', 5),
    'categorization_model' => env('NUMEN_TAXONOMY_MODEL', 'claude-haiku-4-5-20251001'),
    'categorization_provider' => env('NUMEN_TAXONOMY_PROVIDER', 'anthropic'),

    // Slug generation
    'slug_separator' => '-',
    'slug_max_length' => 255,

    // Tree depth limits
    'max_depth' => env('NUMEN_TAXONOMY_MAX_DEPTH', 10),
],
```

---

## 9. File Structure Summary

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── TaxonomyController.php
│   │       ├── TaxonomyTermController.php
│   │       └── ContentTaxonomyController.php
│   └── Resources/
│       ├── VocabularyResource.php
│       ├── TaxonomyTermResource.php
│       └── TaxonomyTermTreeResource.php
├── Models/
│   ├── Vocabulary.php
│   └── TaxonomyTerm.php
├── Services/
│   └── Taxonomy/
│       ├── TaxonomyService.php
│       └── TaxonomyCategorizationService.php
├── Jobs/
│   └── CategorizePipelineContent.php
database/
└── migrations/
    ├── 2026_03_07_000001_create_vocabularies_table.php
    ├── 2026_03_07_000002_create_taxonomy_terms_table.php
    └── 2026_03_07_000003_create_content_taxonomy_table.php
resources/js/
├── Pages/
│   └── Taxonomy/
│       ├── Index.vue
│       ├── Show.vue
│       └── VocabularyForm.vue
└── Components/
    └── Taxonomy/
        ├── TermTree.vue
        ├── TermTreeNode.vue
        ├── TermForm.vue
        ├── TermPicker.vue
        ├── TermBadge.vue
        └── TermBreadcrumb.vue
```

---

## 10. Trade-offs & Decisions

| Decision | Chosen | Alternative | Rationale |
|----------|--------|-------------|-----------|
| Tree storage | Adjacency list + materialized path | Nested sets / closure table | Adjacency list is simpler for writes, materialized path handles ancestor queries without recursive CTEs |
| Pivot table | Dedicated `content_taxonomy` | Polymorphic `taggables` | Explicit pivot is clearer, typed, and allows content-specific columns (confidence); polymorphic can be added later |
| Term slugs | Unique per vocabulary | Globally unique | Per-vocabulary uniqueness allows "news" to exist in both "Categories" and "Topics" |
| Content count | Denormalized column | Always count via query | Read performance matters for API delivery; recalculation is cheap |
| AI categorization | Separate pipeline stage | Post-publish hook | Pipeline stage allows human review before publish; hook is fire-and-forget |
| Drag-and-drop | vuedraggable/SortableJS | Custom implementation | Mature library, Vue 3 support, handles nested drag natively |

---

## 11. Implementation Order

1. **Phase 1 — Schema & Models** (2-3 days)
   - Create migrations
   - Create Vocabulary and TaxonomyTerm models
   - Add relationships to Content and Space
   - Write model tests

2. **Phase 2 — Service Layer & API** (3-4 days)
   - Implement TaxonomyService
   - Build controllers and API resources
   - Add routes
   - Write API tests

3. **Phase 3 — Admin UI** (3-4 days)
   - Vocabulary management pages
   - Term tree editor with drag-and-drop
   - TermPicker integration in content editor

4. **Phase 4 — AI Categorization** (2-3 days)
   - Implement TaxonomyCategorizationService
   - Add pipeline stage
   - Build categorization prompt engineering

5. **Phase 5 — Content Delivery Enhancement** (1-2 days)
   - Taxonomy filtering on content API
   - Term listing pages
   - SEO metadata

**Total estimated effort: 11-16 days**

---

*Architecture designed by Blueprint 🏗️ — Numen Software Architect*
