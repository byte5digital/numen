# Taxonomy API Reference

**Version:** 1.0.0  
**Base URL:** `/api/v1`  
**Added:** 2026-03-07

All taxonomy endpoints are prefixed with `/api/v1/`. Use the `X-Space` header to scope requests to a space (defaults to `default`).

---

## Authentication

| Endpoint type | Auth required |
|--------------|---------------|
| Read (`GET`) | ❌ Public |
| Write (`POST` / `PUT` / `DELETE`) | ✅ Sanctum bearer token |

```http
Authorization: Bearer <your-sanctum-token>
X-Space: my-space-slug
Content-Type: application/json
```

> **Security note:** The space is always derived server-side from the `X-Space` header. Never pass `space_id` in request bodies — it is ignored and exists only in the server response for informational purposes.

---

## Rate Limits

Public taxonomy endpoints follow the standard read throttle: **60 requests/minute**.

---

## Vocabularies

### `GET /api/v1/taxonomies`

List all vocabularies for the current space, ordered by `sort_order`.

**Headers:**

| Header | Required | Description |
|--------|----------|-------------|
| `X-Space` | No | Space slug. Defaults to `default`. |

**curl example:**

```bash
curl https://your-numen-instance.com/api/v1/taxonomies \
  -H "X-Space: my-blog"
```

**Response `200 OK`:**

```json
{
  "data": [
    {
      "id": "01hx9abc123def456ghi789jkl",
      "space_id": "01hy1mno234pqr567stu890vwx",
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
    },
    {
      "id": "01hz2bcd345efg678hij901klm",
      "space_id": "01hy1mno234pqr567stu890vwx",
      "name": "Tags",
      "slug": "tags",
      "description": null,
      "hierarchy": false,
      "allow_multiple": true,
      "settings": null,
      "sort_order": 1,
      "terms_count": 47,
      "created_at": "2026-03-07T08:00:00+00:00",
      "updated_at": "2026-03-07T08:00:00+00:00"
    }
  ]
}
```

---

### `GET /api/v1/taxonomies/{vocabSlug}`

Show a vocabulary with its complete term tree.

**Path parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `vocabSlug` | string | The vocabulary's URL slug (e.g. `categories`) |

**curl example:**

```bash
curl https://your-numen-instance.com/api/v1/taxonomies/categories \
  -H "X-Space: my-blog"
```

**Response `200 OK`:**

```json
{
  "data": {
    "vocabulary": {
      "id": "01hx9abc123def456ghi789jkl",
      "name": "Categories",
      "slug": "categories",
      "hierarchy": true,
      "allow_multiple": true,
      "settings": null,
      "sort_order": 0,
      "terms_count": 12,
      "created_at": "2026-03-07T08:00:00+00:00",
      "updated_at": "2026-03-07T08:00:00+00:00"
    },
    "tree": [
      {
        "id": "01ha1aaa111bbb222ccc333ddd",
        "name": "Technology",
        "slug": "technology",
        "depth": 0,
        "sort_order": 0,
        "content_count": 42,
        "metadata": null,
        "children": [
          {
            "id": "01hb2bbb222ccc333ddd444eee",
            "name": "Web Development",
            "slug": "web-development",
            "depth": 1,
            "sort_order": 0,
            "content_count": 28,
            "metadata": null,
            "children": [
              {
                "id": "01hc3ccc333ddd444eee555fff",
                "name": "Laravel",
                "slug": "laravel",
                "depth": 2,
                "sort_order": 0,
                "content_count": 15,
                "metadata": { "color": "#FF2D20" },
                "children": []
              }
            ]
          }
        ]
      }
    ]
  }
}
```

---

### `POST /api/v1/taxonomies`

Create a new vocabulary. Requires authentication.

**curl example:**

```bash
curl -X POST https://your-numen-instance.com/api/v1/taxonomies \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Topics",
    "description": "Content topics for navigation",
    "hierarchy": true,
    "allow_multiple": true,
    "sort_order": 2
  }'
```

**Request body:**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | string | ✅ | Max 255 chars |
| `slug` | string | No | Auto-generated from name. Pattern: `^[a-z0-9]+(?:-[a-z0-9]+)*$`. Must be unique within the space. |
| `description` | string | No | Max 5000 chars |
| `hierarchy` | boolean | No | Default: `true` |
| `allow_multiple` | boolean | No | Default: `true` |
| `settings` | object | No | Arbitrary JSON. Max 50 keys. |
| `sort_order` | integer | No | Default: `0`. Range: 0–9999. |

**Response `201 Created`:** Returns the created `VocabularyResource`.

**Laravel/PHP example:**

```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->withHeaders(['X-Space' => 'my-blog'])
    ->post('/api/v1/taxonomies', [
        'name'           => 'Topics',
        'hierarchy'      => true,
        'allow_multiple' => true,
        'sort_order'     => 2,
    ]);

$vocabulary = $response->json('data');
```

---

### `PUT /api/v1/taxonomies/{id}`

Update a vocabulary. Requires authentication. Vocabulary must belong to the space in `X-Space`.

All fields are optional (partial update).

**curl example:**

```bash
curl -X PUT https://your-numen-instance.com/api/v1/taxonomies/01hx9abc123def456ghi789jkl \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog" \
  -H "Content-Type: application/json" \
  -d '{ "description": "Updated description", "sort_order": 3 }'
```

**Response `200 OK`:** Returns the updated `VocabularyResource`.

---

### `DELETE /api/v1/taxonomies/{id}`

Delete a vocabulary and cascade-delete all its terms and content assignments. Requires authentication.

**curl example:**

```bash
curl -X DELETE https://your-numen-instance.com/api/v1/taxonomies/01hx9abc123def456ghi789jkl \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog"
```

**Response `200 OK`:**

```json
{ "data": { "deleted": true } }
```

> ⚠️ **Destructive.** This removes the vocabulary, all its terms, and every `content_taxonomy` assignment for those terms. There is no undo.

---

## Terms

### `GET /api/v1/taxonomies/{vocabSlug}/terms`

List terms for a vocabulary. Returns a flat list by default; pass `?tree=1` for nested output.

**Query parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `tree` | boolean | `0` | Return a nested tree structure |

**curl — flat list:**

```bash
curl "https://your-numen-instance.com/api/v1/taxonomies/categories/terms" \
  -H "X-Space: my-blog"
```

**curl — tree:**

```bash
curl "https://your-numen-instance.com/api/v1/taxonomies/categories/terms?tree=1" \
  -H "X-Space: my-blog"
```

**Response `200 OK` (flat):**

```json
{
  "data": [
    {
      "id": "01ha1aaa111bbb222ccc333ddd",
      "vocabulary_id": "01hx9abc123def456ghi789jkl",
      "parent_id": null,
      "name": "Technology",
      "slug": "technology",
      "description": null,
      "path": "/01ha1aaa111bbb222ccc333ddd",
      "depth": 0,
      "sort_order": 0,
      "metadata": null,
      "content_count": 42,
      "created_at": "2026-03-07T08:00:00+00:00",
      "updated_at": "2026-03-07T08:00:00+00:00"
    }
  ]
}
```

---

### `GET /api/v1/taxonomies/{vocabSlug}/terms/{termSlug}`

Show a single term.

**curl example:**

```bash
curl https://your-numen-instance.com/api/v1/taxonomies/categories/terms/technology \
  -H "X-Space: my-blog"
```

**Response `200 OK`:**

```json
{
  "data": {
    "id": "01ha1aaa111bbb222ccc333ddd",
    "vocabulary_id": "01hx9abc123def456ghi789jkl",
    "parent_id": null,
    "name": "Technology",
    "slug": "technology",
    "description": "Technology-related content",
    "path": "/01ha1aaa111bbb222ccc333ddd",
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

### `GET /api/v1/taxonomies/{vocabSlug}/terms/{termSlug}/content`

List published content items tagged with a specific term.

**Query parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `per_page` | integer | `20` | Items per page. Max 100. |
| `include_descendants` | boolean | `false` | Include content tagged with any child/grandchild term. |

**curl example:**

```bash
# All content in "Technology" including child terms (Web Dev, Laravel, etc.)
curl "https://your-numen-instance.com/api/v1/taxonomies/categories/terms/technology/content?include_descendants=true&per_page=10" \
  -H "X-Space: my-blog"
```

**Response `200 OK`:** Paginated `ContentResource` collection (same schema as `GET /api/v1/content`).

---

### `POST /api/v1/taxonomies/{vocabId}/terms`

Create a term within a vocabulary. Requires authentication.

> **Note:** The URL path uses the vocabulary **ID** (ULID), not its slug.

**curl example:**

```bash
curl -X POST https://your-numen-instance.com/api/v1/taxonomies/01hx9abc123def456ghi789jkl/terms \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Laravel",
    "parent_id": "01hb2bbb222ccc333ddd444eee",
    "description": "The Laravel PHP framework",
    "metadata": { "color": "#FF2D20", "icon": "laravel" }
  }'
```

**Request body:**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | string | ✅ | Max 255 chars |
| `slug` | string | No | Auto-generated. Unique within vocabulary. Pattern: `^[a-z0-9]+(?:-[a-z0-9]+)*$` |
| `parent_id` | string (ULID) | No | Must belong to the same vocabulary |
| `description` | string | No | Max 5000 chars |
| `sort_order` | integer | No | Default: `0`. Range: 0–9999 |
| `metadata` | object | No | Arbitrary JSON. Max 50 keys. |

**Response `201 Created`:** Returns the created `TaxonomyTermResource`.

**Laravel/PHP example:**

```php
use App\Services\Taxonomy\TaxonomyService;

// Via service (recommended in backend code)
$term = app(TaxonomyService::class)->createTerm($vocabulary->id, [
    'name'        => 'Laravel',
    'parent_id'   => $webDevTerm->id,
    'description' => 'The Laravel PHP framework',
    'metadata'    => ['color' => '#FF2D20'],
]);
```

---

### `PUT /api/v1/terms/{id}`

Update a term. Requires authentication. Term must belong to the space in `X-Space`. All fields optional.

When `parent_id` changes, the materialized `path` and `depth` are recomputed for the term **and all its descendants** automatically.

**curl example:**

```bash
curl -X PUT https://your-numen-instance.com/api/v1/terms/01hc3ccc333ddd444eee555fff \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog" \
  -H "Content-Type: application/json" \
  -d '{ "name": "Laravel 11", "metadata": { "color": "#FF2D20", "version": "11" } }'
```

**Response `200 OK`:** Returns the updated `TaxonomyTermResource`.

**Error responses:**

| Status | Condition |
|--------|-----------|
| `404` | Term not found or does not belong to the space |
| `422` | `parent_id` belongs to a different vocabulary, or circular reference detected |

---

### `DELETE /api/v1/terms/{id}`

Delete a term. Requires authentication.

**Query parameters:**

| Param | Values | Default | Description |
|-------|--------|---------|-------------|
| `child_strategy` | `reparent`, `cascade` | `reparent` | What to do with child terms |

| Strategy | Behavior |
|----------|----------|
| `reparent` | Child terms move up to the deleted term's parent (safe default) |
| `cascade` | The term and its entire subtree are permanently deleted |

**curl example:**

```bash
# Delete term, move children up
curl -X DELETE "https://your-numen-instance.com/api/v1/terms/01hc3ccc333ddd444eee555fff?child_strategy=reparent" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog"

# Delete term and all descendants
curl -X DELETE "https://your-numen-instance.com/api/v1/terms/01hc3ccc333ddd444eee555fff?child_strategy=cascade" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog"
```

**Response `200 OK`:**

```json
{ "data": { "deleted": true } }
```

---

### `POST /api/v1/terms/{id}/move`

Move a term to a new parent (drag-and-drop reparenting). Recomputes paths for the entire affected subtree. Requires authentication.

**curl example:**

```bash
# Reparent under a new term
curl -X POST https://your-numen-instance.com/api/v1/terms/01hc3ccc333ddd444eee555fff/move \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog" \
  -H "Content-Type: application/json" \
  -d '{ "parent_id": "01hd4ddd444eee555fff666ggg" }'

# Promote to root
curl -X POST https://your-numen-instance.com/api/v1/terms/01hc3ccc333ddd444eee555fff/move \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog" \
  -H "Content-Type: application/json" \
  -d '{ "parent_id": null }'
```

**Request body:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `parent_id` | string\|null | ✅ | New parent ULID, or `null` to promote to root. Must belong to the same vocabulary. |

**Response `200 OK`:** Returns the updated `TaxonomyTermResource`.

**Error responses:**

| Status | Condition |
|--------|-----------|
| `422` | Circular reference (moving into a descendant) |
| `422` | `parent_id` belongs to a different vocabulary |

---

### `POST /api/v1/terms/reorder`

Batch-update `sort_order` for siblings (used by drag-and-drop UI). Requires authentication. All term IDs must belong to the current space.

**curl example:**

```bash
curl -X POST https://your-numen-instance.com/api/v1/terms/reorder \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog" \
  -H "Content-Type: application/json" \
  -d '{
    "ordering": {
      "01ha1aaa111bbb222ccc333ddd": 0,
      "01hb2bbb222ccc333ddd444eee": 1,
      "01hc3ccc333ddd444eee555fff": 2
    }
  }'
```

**Request body:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `ordering` | object | ✅ | Map of `{ "term_id": sort_order_int }`. All IDs must belong to this space. |

**Response `200 OK`:**

```json
{ "data": { "reordered": true } }
```

**Error responses:**

| Status | Condition |
|--------|-----------|
| `403` | One or more term IDs do not belong to this space |

---

## Content ↔ Terms

### `GET /api/v1/content/{slug}/terms`

List all taxonomy terms assigned to a published content item.

**curl example:**

```bash
curl https://your-numen-instance.com/api/v1/content/my-laravel-tutorial/terms
```

**Response `200 OK`:**

```json
{
  "data": [
    {
      "id": "01hc3ccc333ddd444eee555fff",
      "vocabulary_id": "01hx9abc123def456ghi789jkl",
      "name": "Laravel",
      "slug": "laravel",
      "depth": 2,
      "content_count": 15,
      "metadata": { "color": "#FF2D20" }
    },
    {
      "id": "01he5eee555fff666ggg777hhh",
      "vocabulary_id": "01hz2bcd345efg678hij901klm",
      "name": "tutorial",
      "slug": "tutorial",
      "depth": 0,
      "content_count": 38,
      "metadata": null
    }
  ]
}
```

---

### `POST /api/v1/content/{id}/terms`

**Additive** — assign terms to content without removing existing assignments. Requires authentication.

**curl example:**

```bash
curl -X POST https://your-numen-instance.com/api/v1/content/01hd4ddd444eee555fff666ggg/terms \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog" \
  -H "Content-Type: application/json" \
  -d '{
    "assignments": [
      { "term_id": "01hc3ccc333ddd444eee555fff", "sort_order": 0 },
      { "term_id": "01he5eee555fff666ggg777hhh", "sort_order": 1 }
    ]
  }'
```

**Request body:**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `assignments` | array | ✅ | Array of assignment objects |
| `assignments[].term_id` | string (ULID) | ✅ | Must belong to the same space |
| `assignments[].sort_order` | integer | No | Default: `0`. Range: 0–9999 |
| `assignments[].auto_assigned` | boolean | No | Default: `false` |
| `assignments[].confidence` | float | No | AI score. Range: 0.0–1.0 |

**Response `200 OK`:**

```json
{ "data": { "assigned": true } }
```

---

### `PUT /api/v1/content/{id}/terms`

**Sync (replace)** — replaces all existing term assignments with the provided list. Pass an empty array to remove all assignments. Requires authentication.

**curl example:**

```bash
# Replace all assignments
curl -X PUT https://your-numen-instance.com/api/v1/content/01hd4ddd444eee555fff666ggg/terms \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog" \
  -H "Content-Type: application/json" \
  -d '{ "term_ids": ["01hc3ccc333ddd444eee555fff", "01he5eee555fff666ggg777hhh"] }'

# Remove all assignments
curl -X PUT https://your-numen-instance.com/api/v1/content/01hd4ddd444eee555fff666ggg/terms \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog" \
  -H "Content-Type: application/json" \
  -d '{ "term_ids": [] }'
```

**Request body:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `term_ids` | array | ✅ (present) | Array of term ULIDs. All must belong to this space. Empty array removes all. |

**Response `200 OK`:**

```json
{ "data": { "synced": true } }
```

---

### `DELETE /api/v1/content/{id}/terms/{termId}`

Remove a single term assignment from a content item. Requires authentication.

**curl example:**

```bash
curl -X DELETE https://your-numen-instance.com/api/v1/content/01hd4ddd444eee555fff666ggg/terms/01hc3ccc333ddd444eee555fff \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog"
```

**Response `200 OK`:**

```json
{ "data": { "removed": true } }
```

---

### `POST /api/v1/content/{id}/auto-categorize`

Dispatch an AI categorization job. The job analyzes the content body, scores all available vocabulary terms, and assigns those above the configured confidence threshold (`NUMEN_TAXONOMY_AUTO_ASSIGN_THRESHOLD`, default `0.7`). Requires authentication.

**curl example:**

```bash
curl -X POST https://your-numen-instance.com/api/v1/content/01hd4ddd444eee555fff666ggg/auto-categorize \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Space: my-blog"
```

**Response `200 OK`:**

```json
{
  "data": {
    "queued": true,
    "content_id": "01hd4ddd444eee555fff666ggg"
  }
}
```

The job runs asynchronously. Check `content_taxonomy` assignments (via `GET /api/v1/content/{slug}/terms`) after a moment to see the AI-assigned terms. All AI-assigned terms have `auto_assigned: true` and a `confidence` score on the pivot.

---

## Filtering Content by Taxonomy

The content delivery endpoint (`GET /api/v1/content`) supports taxonomy-based filtering via query parameters.

**Syntax:** `?taxonomy[{vocabSlug}]={termSlug},{termSlug}`

```bash
# Content tagged with 'laravel' OR 'php' in the 'categories' vocabulary
curl "https://your-numen-instance.com/api/v1/content?taxonomy[categories]=laravel,php" \
  -H "X-Space: my-blog"

# Content tagged with 'tutorial' in 'tags'
curl "https://your-numen-instance.com/api/v1/content?taxonomy[tags]=tutorial" \
  -H "X-Space: my-blog"

# Combined: categories=laravel AND tags=tutorial
curl "https://your-numen-instance.com/api/v1/content?taxonomy[categories]=laravel&taxonomy[tags]=tutorial" \
  -H "X-Space: my-blog"
```

---

## Error Reference

All error responses follow the standard Laravel JSON format:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "parent_id": ["The parent term does not belong to this vocabulary."]
  }
}
```

| Status | Meaning |
|--------|---------|
| `401` | Missing or invalid bearer token |
| `403` | Space ownership violation (term/vocabulary belongs to a different space) |
| `404` | Vocabulary, term, or content not found |
| `422` | Validation failure (see `errors` key) |
| `429` | Rate limit exceeded |

---

## Resource Schemas

### `VocabularyResource`

```json
{
  "id": "string (ULID)",
  "space_id": "string (ULID)",
  "name": "string",
  "slug": "string",
  "description": "string|null",
  "hierarchy": "boolean",
  "allow_multiple": "boolean",
  "settings": "object|null",
  "sort_order": "integer",
  "terms_count": "integer",
  "created_at": "ISO 8601 datetime",
  "updated_at": "ISO 8601 datetime"
}
```

### `TaxonomyTermResource`

```json
{
  "id": "string (ULID)",
  "vocabulary_id": "string (ULID)",
  "parent_id": "string (ULID)|null",
  "name": "string",
  "slug": "string",
  "description": "string|null",
  "path": "string",
  "depth": "integer",
  "sort_order": "integer",
  "metadata": "object|null",
  "content_count": "integer",
  "created_at": "ISO 8601 datetime",
  "updated_at": "ISO 8601 datetime"
}
```

### `TaxonomyTermTreeResource`

Same fields as `TaxonomyTermResource`, plus:

```json
{
  "children": "TaxonomyTermTreeResource[]"
}
```

Children are recursively nested. Leaf nodes have `"children": []`.

---

*Documentation by Scribe 📝 — Numen Technical Writing*
