# Migration Wizard

The Migration Wizard enables seamless content migration from external CMS platforms into Numen. It provides a guided 5-step workflow with AI-assisted field mapping, incremental sync, and full rollback support.

## Supported CMS Platforms

| Platform   | Detection | Schema | Content | Media | Taxonomy | Users |
|------------|-----------|--------|---------|-------|----------|-------|
| WordPress  | ✅        | ✅     | ✅      | ✅    | ✅       | ✅    |
| Strapi     | ✅        | ✅     | ✅      | ✅    | ✅       | ✅    |
| Contentful | ✅        | ✅     | ✅      | ✅    | ✅       | ✅    |
| Ghost      | ✅        | ✅     | ✅      | ✅    | ✅       | ✅    |
| Directus   | ✅        | ✅     | ✅      | ✅    | ✅       | ✅    |
| Payload    | ✅        | ✅     | ✅      | ✅    | ✅       | ✅    |

## Architecture

```
┌──────────────────────────────────────────────────────┐
│                  Vue Wizard UI                        │
│  Step 1: Connect → 2: Schema → 3: Map → 4: Run → 5  │
└──────────────┬───────────────────────────────────────┘
               │ REST API
┌──────────────▼───────────────────────────────────────┐
│              API Controllers                          │
│  Session · Detect · Schema · Mapping · Execute        │
│  Media · Rollback · Sync                              │
└──────────────┬───────────────────────────────────────┘
               │
┌──────────────▼───────────────────────────────────────┐
│              Services                                 │
│  CmsDetector · SchemaInspector · AiFieldMapping       │
│  ContentTransformPipeline · ContentTransformer        │
│  TaxonomyImport · MediaImport · UserImport            │
│  MigrationExecutor · RollbackService · DeltaSync      │
└──────────────┬───────────────────────────────────────┘
               │
┌──────────────▼───────────────────────────────────────┐
│              Connectors                               │
│  WordPress · Strapi · Contentful · Ghost              │
│  Directus · Payload                                   │
└──────────────────────────────────────────────────────┘
```

## Workflow

### Step 1: Connect & Detect
Auto-detect the source CMS type and version from a URL.

```
POST /api/v1/spaces/{space}/migrations/detect
{ "url": "https://myblog.com", "credentials": { "username": "admin", "password": "..." } }
```

### Step 2: Schema Inspection
Fetch and compare source content types against Numen's schema.

```
GET /api/v1/spaces/{space}/migrations/{session}/schema
GET /api/v1/spaces/{space}/migrations/{session}/schema/compare
```

### Step 3: Field Mapping
AI-assisted mapping of source fields to Numen content types. Supports manual overrides.

```
POST /api/v1/spaces/{space}/migrations/{session}/mappings/suggest
PUT  /api/v1/spaces/{space}/migrations/{session}/mappings
GET  /api/v1/spaces/{space}/migrations/{session}/mappings/preview
```

### Step 4: Execute Migration
Runs taxonomy import → content transform → chunk-based import via queued jobs.

```
POST /api/v1/spaces/{space}/migrations/{session}/execute
GET  /api/v1/spaces/{space}/migrations/{session}/progress
POST /api/v1/spaces/{space}/migrations/{session}/pause
POST /api/v1/spaces/{space}/migrations/{session}/resume
```

### Step 5: Review & Media Import

```
POST /api/v1/spaces/{space}/migrations/{session}/media
GET  /api/v1/spaces/{space}/migrations/{session}/media/progress
```

## Post-Migration Operations

### Rollback
Undo a completed migration by deleting all imported content, media, taxonomies, and users.

```
POST /api/v1/spaces/{space}/migrations/{session}/rollback
```

**Response:**
```json
{
  "message": "Migration rolled back successfully.",
  "data": {
    "session_id": "01HXYZ...",
    "status": "rolled_back",
    "contentDeleted": 42,
    "mediaDeleted": 15,
    "taxonomiesDeleted": 8,
    "usersDeleted": 3
  }
}
```

**Safety:** Only migrations with `completed` status can be rolled back. The operation uses database transactions for atomicity.

### Delta Sync
Perform an incremental sync to import only new or changed content since the last migration run.

```
POST /api/v1/spaces/{space}/migrations/{session}/sync
```

**Response:**
```json
{
  "message": "Delta sync completed.",
  "data": {
    "session_id": "01HXYZ...",
    "status": "synced",
    "created": 5,
    "updated": 3,
    "unchanged": 34,
    "failed": 0
  }
}
```

Delta sync uses content hashes to detect changes and checkpoint cursors to track pagination position.

## Data Model

### MigrationSession
Central record tracking the migration state, source CMS details, credentials, and progress counters.

**Statuses:** `pending` → `mapped` → `running` → `completed` → `synced` | `rolled_back` | `failed` | `paused`

### MigrationTypeMapping
Maps a source content type to a Numen content type with field-level mapping configuration. Supports AI suggestions.

### MigrationItem
Tracks individual content items through the migration pipeline: fetched → transformed → completed/failed. Stores source hash for delta sync detection.

### MigrationCheckpoint
Records pagination cursors per content type for resumable imports and delta sync.

## Session CRUD

```
GET    /api/v1/spaces/{space}/migrations              — List sessions
POST   /api/v1/spaces/{space}/migrations              — Create session
GET    /api/v1/spaces/{space}/migrations/{session}     — Show session
PATCH  /api/v1/spaces/{space}/migrations/{session}     — Update session
DELETE /api/v1/spaces/{space}/migrations/{session}     — Delete session
```

## Authentication
All endpoints require Sanctum authentication via `Authorization: Bearer <token>`.

## Error Handling
- **404:** Session not found or belongs to a different space.
- **422:** Invalid operation for the current session status (e.g., rollback on a running migration).
- **500:** Unexpected server error during migration processing.
