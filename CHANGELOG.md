# Changelog

All notable changes to Numen will be documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

**Pre-1.0 note:** Breaking changes can occur in any `0.x.0` minor bump. They'll always be documented here. See the [Architecture Review](docs/ARCHITECTURE_REVIEW_V1.md) for the versioning policy and roadmap to 1.0.

---


## [0.9.0] — 2026-03-15

### Added

**AI-Powered Content Knowledge Graph** ([Discussion #14](https://github.com/byte5digital/numen/discussions/14))

Automatically maps relationships between content items into an interactive knowledge graph, enabling related content discovery, topic clustering, content gap analysis, and D3.js visualization.

**Features:**
- **Entity extraction:** AI extracts named entities (persons, organizations, locations, concepts) from content body using Claude
- **5 edge types:** Semantic similarity (vector embeddings), co-tag (shared taxonomy), co-author (same author), sequential (series order), co-entity (shared named entities)
- **Topic clustering:** DBSCAN/k-means clustering groups semantically related content into named topic clusters
- **Content gap analysis:** Identifies under-covered topic clusters relative to audience demand signals, with suggested topics
- **D3.js visualization:** Force-directed interactive graph in Numen Studio at `/studio/graph/{spaceId}` — nodes colour-coded by cluster, edge thickness indicates weight
- **Related content widget:** `GET /api/v1/graph/related/{contentId}` powers headless frontend sidebars and bottom-of-page recommendations
- **Shortest path:** Finds the connection path between any two content nodes for content journey debugging
- **REST API:** 7 endpoints covering related content, clusters, cluster contents, content gaps, shortest path, node metadata, and manual reindex

**Endpoints:**
- `GET /api/v1/graph/related/{contentId}` — Related content with edge type filtering
- `GET /api/v1/graph/clusters` — Topic cluster summaries for a space
- `GET /api/v1/graph/clusters/{clusterId}` — Contents of a specific cluster
- `GET /api/v1/graph/gaps` — Content gap analysis with gap scores
- `GET /api/v1/graph/path/{fromId}/{toId}` — Shortest path between two nodes
- `GET /api/v1/graph/node/{contentId}` — Graph node metadata
- `POST /api/v1/graph/reindex/{contentId}` — Trigger re-indexing (admin)

**New environment variables:**
- `GRAPH_ENABLED=true`
- `GRAPH_SIMILARITY_THRESHOLD=0.75`
- `GRAPH_MAX_EDGES_PER_TYPE=20`

---

## [0.8.0] — 2026-03-15

### Added

**AI Content Repurposing Engine** ([Discussion #10](https://github.com/byte5digital/numen/discussions/10))

One-click content repurposing to 8 formats with AI-powered tone preservation and brand consistency.

**Features:**
- **8 supported formats:** Twitter thread, LinkedIn post, Newsletter section, Instagram caption, Podcast script outline, Product page copy, FAQ section, YouTube description
- **AI-powered:** Uses existing Persona/LLM system for tone-aware, brand-consistent repurposing
- **Async processing:** Leverages `ai-pipeline` queue for background repurposing tasks
- **Batch operations:** Repurpose up to 50 items in a single request with cost estimation
- **Custom templates:** Per-space format templates with global defaults
- **Staleness detection:** Automatic re-repurposing when source content is updated
- **REST API:** Full CRUD endpoints for templates, single and batch repurposing, status polling, and cost estimation

**Endpoints:**
- `POST /v1/content/{content}/repurpose` — Trigger single repurposing
- `GET /v1/content/{content}/repurposed` — List repurposed items
- `GET /v1/repurposed/{id}` — Poll repurposing status
- `GET /v1/spaces/{space}/repurpose/estimate` — Cost estimation
- `POST /v1/spaces/{space}/repurpose/batch` — Batch repurposing (50 item limit)
- `GET /v1/format-templates` — List templates
- `POST /v1/format-templates` — Create template
- `PATCH /v1/format-templates/{template}` — Update template
- `DELETE /v1/format-templates/{template}` — Delete template
- `GET /v1/format-templates/supported` — List 8 supported formats

---
## [Unreleased]

### Added

**AI Pipeline Templates & Preset Library** ([Issue #36](https://github.com/byte5digital/numen/issues/36))

Reusable AI pipeline templates for accelerated content creation workflows, featuring 8 built-in templates, community library, space-scoped templates, one-click install wizard, template versioning, and plugin registration hooks.

**Features:**
- **8 built-in templates:** Blog Post Pipeline, Social Media Campaign, Product Description, Email Newsletter, Press Release, Landing Page, Technical Documentation, Video Script
- **Template library API:** Discover, rate, and install templates with metadata
- **Space-scoped templates:** Custom templates per content space with full RBAC support
- **Install wizard:** Auto-configures personas, stages, and input variables from template schema
- **Template versioning:** Track changes, publish/unpublish versions, rollback support
- **Template packs:** Plugin system for registering template collections
- **Community ratings:** Rate and provide feedback on templates
- **Metadata support:** Categories, icons, author info, and schema versioning
- **Security:** Space-scoped installs, RBAC permission gates, audit logging

**Endpoints:**
- `GET /api/v1/spaces/{space}/pipeline-templates` — List templates (paginated)
- `POST /api/v1/spaces/{space}/pipeline-templates` — Create custom template
- `GET /api/v1/spaces/{space}/pipeline-templates/{template}` — Get template details
- `PATCH /api/v1/spaces/{space}/pipeline-templates/{template}` — Update template
- `DELETE /api/v1/spaces/{space}/pipeline-templates/{template}` — Delete template
- `POST /api/v1/spaces/{space}/pipeline-templates/{template}/publish` — Publish template
- `POST /api/v1/spaces/{space}/pipeline-templates/{template}/unpublish` — Unpublish template
- `GET /api/v1/spaces/{space}/pipeline-templates/{template}/versions` — List versions
- `POST /api/v1/spaces/{space}/pipeline-templates/{template}/versions` — Create version
- `GET /api/v1/spaces/{space}/pipeline-templates/{template}/versions/{version}` — Get version
- `POST /api/v1/spaces/{space}/pipeline-templates/installs/{version}` — Install template (rate-limited 5/min)
- `PATCH /api/v1/spaces/{space}/pipeline-templates/installs/{install}` — Update install
- `DELETE /api/v1/spaces/{space}/pipeline-templates/installs/{install}` — Remove install
- `GET /api/v1/spaces/{space}/pipeline-templates/{template}/ratings` — List ratings
- `POST /api/v1/spaces/{space}/pipeline-templates/{template}/ratings` — Rate template

**Plugin Hooks:**
- `registerTemplateCategory(array $category)` — Register custom template categories
- `registerTemplatePack(array $pack)` — Register template collections from plugins

**Models:**
- `PipelineTemplate` — Template metadata (name, slug, category, icon, author)
- `PipelineTemplateVersion` — Versioned template definitions with JSON schema
- `PipelineTemplateInstall` — Track template usage per space
- `PipelineTemplateRating` — Community feedback (1-5 stars)

**New environment variables:**
- `TEMPLATE_LIBRARY_ENABLED=true`
- `TEMPLATE_INSTALL_RATE_LIMIT=5` (per minute)

See [docs/pipeline-templates.md](docs/pipeline-templates.md) for complete documentation.



### Added

- Webhooks admin UI — manage webhook endpoints, event subscriptions, delivery logs, and secret rotation directly from the admin panel (Settings → Webhooks)

## [0.8.0] — 2026-03-15

### Added

**Media Library & Digital Asset Management** ([Discussion #4](https://github.com/byte5digital/numen/discussions/4))

A complete digital asset management (DAM) system for organizing, tagging, editing, and serving media assets. Built for multi-format content delivery and CDN integration.

**Features:**

- **Folders & Collections** — Organize assets hierarchically using adjacency-list folders. Create smart collections with powerful filtering and bulk operations.
- **Drag-and-drop Upload** — Metadata extraction (MIME type, dimensions, file size, duration) on ingest. Progress tracking and batch upload support.
- **AI Auto-tagging (opt-in)** — Enable `MEDIA_AI_TAGGING` environment variable to automatically tag images using Claude vision. Powered by Anthropic API; all costs logged to `AIGenerationLog`.
- **Image Editing** — Crop, rotate, and resize images via `MediaEditController`. Changes create new variants; originals are preserved.
- **Automatic Variant Generation** — On upload, generate `thumb` (150×150), `medium` (600×600), and `large` (1600×1600) variants. WebP format with configurable quality. Stored locally or on S3 (via `FILESYSTEM_DISK`).
- **Usage Tracking** — Query which content items reference a specific asset. Prevents accidental deletion of in-use media.
- **Public Headless API** — `/v1/public/media` endpoints (no auth required) with throttle protection (120 req/min). Perfect for headless frontends and CDN edge caching.
- **Full REST API** — Complete CRUD operations on assets, folders, and collections. Bearer token auth via Sanctum.
- **MediaPicker Vue Component** — Integrates with content editor for seamless asset selection during content creation.

**Environment Variables (new):**

- `MEDIA_AI_TAGGING` — Enable automatic AI-based image tagging (default: `false`)
- `CDN_ENABLED` — Enable public CDN delivery endpoints (default: `true`)

**API Endpoints:**

*Authenticated (requires Bearer token):*
- `GET /v1/media` — List all assets
- `POST /v1/media` — Upload asset (20 req/min throttle)
- `GET /v1/media/{asset}` — Fetch asset details
- `PATCH /v1/media/{asset}` — Update asset metadata
- `DELETE /v1/media/{asset}` — Delete asset
- `PATCH /v1/media/{asset}/move` — Move to folder
- `GET /v1/media/{asset}/usage` — Show usage in content
- `POST /v1/media/{asset}/edit` — Edit (crop/rotate/resize)
- `GET /v1/media/{asset}/variants` — List generated variants
- `GET|POST /v1/media/folders` — CRUD folders
- `PATCH /v1/media/folders/{folder}/move` — Move folder
- `GET|POST|PATCH|DELETE /v1/media/collections` — CRUD collections
- `POST|DELETE /v1/media/collections/{collection}/items` — Manage collection items

*Public (no auth):*
- `GET /v1/public/media` — List public assets (120 req/min throttle)
- `GET /v1/public/media/{asset}` — Fetch public asset
- `GET /v1/public/media/collections/{collection}` — Fetch collection

---

### Planned
- Remove legacy `numen.anthropic` config block (duplicates `numen.providers.anthropic`)
- `AgentContract` interface extracted from `Agent` abstract class

---

## [0.9.0] — 2026-03-15

### Added

**Multi-Language & i18n Support** ([Discussion #7](https://github.com/byte5digital/numen/discussions/7))

Full content localization with AI-powered translation, space-level locale management, and intelligent fallback chains.

**Locale Management:**
- Space-level locale configuration: add/remove/reorder locales, set default locale
- Intelligent 5-step fallback chain (exact match → language prefix → fallback config → space default → `"en"`)
- Prevents invalid locale codes with `Locale` validation class (BCP 47 compliant)

**AI-Powered Translation:**
- Tone-aware translation using existing Persona system — respects content creator's voice
- Async job queue (`TranslateContentJob` on `ai-pipeline` queue) for background processing
- Translation status tracking: pending → completed/failed with error logging
- Job retry support with configurable backoff

**Translation Workflow:**
- Translation matrix view showing per-content, per-locale coverage and status
- Side-by-side translation editor for reviewing AI-generated translations
- Manual translation support via API
- Batch translation operations with progress reporting

**REST API Endpoints:**
- **Locale Management:** `GET/POST/PATCH/DELETE /api/v1/locales` (space-scoped)
- **Translation Workflow:** `POST /api/v1/content/{content}/translate`, `GET /api/v1/content/{content}/translations`
- **Translation Matrix:** `GET /api/v1/translations/matrix` with pagination and status filters
- **Supported Locales:** `GET /api/v1/locales/supported` for available BCP 47 codes

**Locale Awareness:**
- Middleware: `SetLocaleFromRequest` respects `Accept-Language` header, `?locale=` query param, and `X-Locale` header
- API responses include current locale context; content delivery selects best-match locale automatically
- Graceful fallback for missing translations (no errors, uses fallback chain)

**CLI:**
- `php artisan numen:setup-i18n {space_id}` — automated migration of existing spaces to i18n (adds default locale + tracks baseline)

**Database Tables:**
- `space_locales` — locale configurations per space (locale code, is_default, sort order)
- `translation_jobs` — async translation job tracking (content_id, from_locale, to_locale, status, result)

**Zero Breaking Changes:**
- Feature is fully additive — existing single-language spaces work unchanged
- No migrations required for spaces that don't use i18n
- Backward compatible with all existing API routes



## [0.7.0] — 2026-03-15

### Added

**Numen CLI** ([Discussion #16](https://github.com/byte5digital/numen/discussions/16))

A full artisan-based CLI for managing content, briefs, pipelines, and system health — designed for server-side automation, CI/CD hooks, and scripted workflows.

**8 CLI commands:**

| Command | Signature |
|---|---|
| Content list | `numen:content:list [--type=] [--status=] [--limit=20]` |
| Content import | `numen:content:import --file=<path> [--space-id=] [--dry-run]` |
| Content export | `numen:content:export [--format=json\|markdown] [--output=] [--type=] [--status=] [--id=]` |
| Brief create | `numen:brief:create --title= [--type=] [--persona=] [--priority=] [--keywords=*] [--no-run]` |
| Brief list | `numen:brief:list [--status=] [--space-id=] [--limit=20]` |
| Pipeline run | `numen:pipeline:run --brief-id= [--pipeline-id=]` |
| Pipeline status | `numen:pipeline:status [--limit=10] [--running] [--pipeline-id=]` |
| System status | `numen:status [--details]` |

**Import/Export:**
- JSON bulk import with `--dry-run` preview mode; skips duplicates by slug
- JSON and Markdown export with content type and status filters
- Export defaults to `storage/exports/<timestamp>.json` when no `--output` given

**System Health Check (`numen:status`):**
- Database connectivity and driver info
- Content stats (spaces, content items, briefs, pipeline runs)
- Cache read/write verification
- Queue driver detection (warns on sync/null in production)
- AI provider configuration (Anthropic, OpenAI, Azure; with `--details` for model info)
- Image generation provider status

### Security

- **File path validation:** `realpath()` used on all file inputs; path traversal sequences (`../`) are rejected outright
- **Import path sandboxing:** warns (but does not block) when `--file` is outside `storage_path()` — CLI is a trusted, privileged interface
- **Export default sandboxing:** `--output` defaults to `storage/exports/`; warns when writing outside `base_path()`
- **Input enum whitelisting:**
  - `ContentImportCommand`: `status` field validated against `[draft, published, archived]`, defaults to `draft`
  - `BriefCreateCommand`: `--priority` validated against `[low, normal, high, urgent]`, defaults to `normal`

---

## [0.2.1] — 2026-03-07

### Fixed
- **Production deploy fix:** `taxonomy_terms.path` index exceeded MySQL's 3072-byte max key length. Now uses a 768-char prefix index on MySQL (`768 × 4 = 3072 bytes`), fitting exactly within the limit.
- **SQLite compatibility:** Prefix indexes are MySQL-specific. Migration now detects the DB driver — uses `rawIndex` with prefix on MySQL, plain `index` on SQLite/others.
- **Taxonomy security hardening:** Fixed circular reference detection in term hierarchy, blocked cross-vocabulary parent assignments, added metadata size guards (max 64KB).

### Tests
- Test suite expanded to 332 tests (752 assertions), all passing.

---

## [0.2.0] — 2026-03-07

### Added

**Taxonomy & Content Organization** ([Discussion #8](https://github.com/byte5digital/numen/discussions/8))
- **Vocabularies:** Flexible vocabulary system — create multiple taxonomy types per space (Categories, Tags, Topics, etc.). Configurable hierarchy and cardinality (`allow_multiple`).
- **Taxonomy Terms:** Hierarchical terms with adjacency list (`parent_id`) + materialized path for fast ancestor queries. SEO-friendly slugs, descriptions, and custom metadata (icon, color, image).
- **Content ↔ Term Relationships:** Many-to-many pivot table (`content_taxonomy`) with sort order, AI auto-assignment tracking, and confidence scores.
- **AI Auto-Categorization:** `TaxonomyCategorizer` service integrates with the AI pipeline to automatically suggest and assign taxonomy terms to content during generation. Confidence scores stored per assignment.
- **Taxonomy Admin UI:** Full CRUD for vocabularies and terms in the admin panel. Tree management with drag-and-drop reordering support.
- **REST API:** Full taxonomy endpoints — CRUD for vocabularies (`/api/v1/taxonomies`), terms (`/api/v1/taxonomies/{id}/terms`), and content assignments (`/api/v1/content/{id}/terms`). OpenAPI spec updated.
- **API Token Management:** Admin UI for creating/revoking Sanctum API tokens. All write API routes now require authentication.
- Multi-provider image generation: OpenAI (GPT Image 1.5), Together AI (FLUX), fal.ai (FLUX/SD3.5/Recraft), Replicate (universal). `ImageManager` factory with per-persona provider config (`generator_provider` / `generator_model`).
- User management (CRUD) with admin frontend pages — list, create, edit, delete users.
- Self-service password change for logged-in users (profile settings page).
- Permanent content deletion with full cascade cleanup (content blocks, versions, media assets, pipeline runs, AI logs).
- Larastan level 5 static analysis — CI job added. All 199 errors fixed, 0 remaining.
- Prominent Swagger UI links on start page.

**New Database Tables:**
- `vocabularies` — taxonomy vocabulary definitions, space-scoped
- `taxonomy_terms` — hierarchical terms with materialized paths
- `content_taxonomy` — polymorphic-ready pivot with AI metadata

**New Models:** `Vocabulary`, `TaxonomyTerm`

**New Services:** `TaxonomyService`, `TaxonomyCategorizer`

**New Controllers:** `TaxonomyAdminController`, `TaxonomyController`, `TaxonomyTermController`

### Fixed
- Cast `content_refresh_days` to `int` for PHP 8.4 strict typing compatibility
- Cache table migration: corrected Laravel schema
- Jobs table migration: corrected Laravel schema
- Missing `DatabaseSeeder.php` — added to prevent bare `db:seed` failures
- `DemoSeeder` synced with live DB: 5 personas, fully idempotent
- Queue worker detection for Laravel Cloud
- Visual Director persona config fields

### Changed
- CI: removed PHP 8.3 from test matrix — Numen requires PHP ^8.4
- Test suite expanded to 332 tests (up from 117 in 0.1.1)

---

## [0.1.1] — 2026-03-06

### Added
- OpenAPI 3.1.0 specification served at `GET /api/documentation`
- Rate limiting on all public API endpoints: 60 req/min for content and pages endpoints, 30 req/min for component types
- Configurable HTTP timeouts per provider via `numen.providers.*.timeout` config key

### Changed
- Removed legacy `AnthropicClient` and `RateLimiter` classes — `LLMManager` is now the sole AI provider interface

### Fixed
- `BriefController` bug in response handling

### Tests
- Expanded test suite from 23 to 117 tests, now covering API endpoints, provider fallback logic, and pipeline execution

---

## [0.1.0] — 2026-03-06

Initial public release. This is the "here's what we have" release — solid architecture, working pipeline, thin test coverage. See the Architecture Review for a frank assessment of what's stable vs. what will change.

### Added

**AI Pipeline Engine**
- Event-driven pipeline executor (`PipelineExecutor`) with queued stage execution
- Three built-in pipeline stage types: `ai_generate`, `ai_review`, `human_gate`, `auto_publish`
- Pipeline run tracking with per-stage results stored in `stage_results` (JSON)
- Auto-publish when Editorial Director quality score ≥ `AI_AUTO_PUBLISH_SCORE` (default 80)
- Human gate support: pipeline pauses at `paused_for_review`, resumes via API

**AI Agent System**
- Abstract `Agent` base class with retry logic and cost tracking hooks
- `AgentFactory` for type-based agent resolution
- Three built-in agents:
  - `ContentCreatorAgent` — full article generation from brief
  - `SeoExpertAgent` — meta title, description, slug, keyword optimization
  - `EditorialDirectorAgent` — quality scoring (0–100) with structured feedback
- AI Personas: configurable system prompts, temperature, max tokens per role

**Multi-Provider LLM Layer**
- `LLMProvider` interface — extend to add custom providers
- `LLMManager` with ordered fallback chain (auto-retries next provider on rate limits or 5xx)
- Built-in providers: Anthropic, OpenAI, Azure OpenAI
- Per-role model assignment via env vars (`AI_MODEL_GENERATION`, `AI_MODEL_SEO`, etc.)
- Cross-provider model equivalents map (route `claude-sonnet-4-6` to `gpt-4o` on OpenAI)
- Cost tracking per API call with daily/monthly/per-content limits

**REST API (`/api/v1/*`)**
- Public content delivery: `GET /content`, `GET /content/{slug}`, `GET /content/type/{type}`
- Public pages API: `GET /pages`, `GET /pages/{slug}`
- Authenticated brief management: `POST /briefs`, `GET /briefs`, `GET /briefs/{id}`
- Pipeline management: `GET /pipeline-runs/{id}`, `POST /pipeline-runs/{id}/approve`
- Personas: `GET /personas`
- Cost analytics: `GET /analytics/costs`
- Component types (headless page builder): `GET /component-types`, `GET /component-types/{type}`
- Sanctum API token authentication

**Data Models**
- 16 Eloquent models: `Content`, `ContentBlock`, `ContentVersion`, `ContentBrief`, `ContentPipeline`, `ContentType`, `Persona`, `Space`, `Page`, `PageComponent`, `ComponentDefinition`, `AIGenerationLog`, `PipelineRun`, `MediaAsset`, `Setting`, `User`
- Block-based content model: each content piece is a set of typed `ContentBlock` records
- Full AI provenance: `AIGenerationLog` records every API call (model, tokens, cost, stage)
- Content versioning: every published version stored in `ContentVersion`

**Admin UI**
- Inertia.js + Vue 3 SPA
- Content management (list, view, approve pipeline runs, permanent deletion)
- Brief creation with keyword and priority controls
- Pipeline run monitoring per content piece
- Persona management
- User management (CRUD) with admin frontend pages
- Self-service password change for logged-in users
- Settings (AI provider config, cost limits, pipeline behavior)
- Cost analytics dashboard

**Configuration**
- `config/numen.php` — single config file for all Numen behavior
- `.env.example` with full documentation of all variables
- Cost limit controls: daily, monthly, per-content-piece caps
- Pipeline behavior: auto-publish threshold, human gate timeout, content refresh interval

**Developer Tooling**
- `DemoSeeder`: creates a `byte5.labs` Space with default Personas and a full pipeline definition
- Laravel Pint config for code style enforcement
- Laravel Sail for optional Docker development

### Known Limitations (0.1.0)

- Test coverage is minimal: 1 feature test, 2 unit tests. *(Fixed in 0.1.1 — 117 tests.)*
- Legacy `AnthropicClient` coexists with `LLMManager` — both work, legacy will be removed in 0.2.0. *(Removed in 0.1.1.)*
- `AnthropicProvider` HTTP timeout is hardcoded at 120s (not configurable yet). *(Fixed in 0.1.1.)*
- No rate limiting on public API endpoints. *(Fixed in 0.1.1.)*
- No OpenAPI/Swagger spec. *(Fixed in 0.1.1.)*
- Image generation (`ai_illustrate` stage type) is defined in the stage vocabulary but not fully implemented.

---

[Unreleased]: https://github.com/byte5digital/numen/compare/v0.2.1...HEAD
[0.2.1]: https://github.com/byte5digital/numen/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/byte5digital/numen/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/byte5digital/numen/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/byte5digital/numen/releases/tag/v0.1.0
