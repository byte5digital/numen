# Changelog

All notable changes to Numen are documented here.

## [Unreleased]

### Added

**Performance Feedback Loop** ([Issue #43](https://github.com/byte5digital/numen/issues/43))

Closed-loop performance analytics that learns from every published piece of content and feeds insights back into the creation pipeline.

**Key Capabilities:**
- **Real-time event ingestion** — Tracking pixel, bulk SDK endpoint, GA4/Segment webhook intake
- **Automated aggregation** — Daily, weekly, and monthly performance snapshots with composite scoring
- **Attribute-metric correlation** — Discovers which content attributes (tone, length, format) drive results
- **Predictive space model** — Builds a space-level model with feature weights and recommendations
- **Actionable insights** — Human-readable insights from snapshots, correlations, and model predictions
- **Brief enrichment** — Automatically injects top-performing attributes into new briefs
- **A/B testing** — Controlled experiments with traffic splitting, conversion tracking, and statistical significance (z-test)
- **Content refresh advisor** — Identifies declining content, scores urgency, suggests improvements, auto-generates refresh briefs
- **Vue dashboard** — 6-component performance dashboard in Numen Studio (overview, detail, A/B manager, refresh advisor, correlations)
- **30+ API endpoints** — Full REST API for tracking, analytics, A/B testing, and content refresh

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

### Added — #37 Competitor-Aware Content Differentiation

#### Infrastructure
- **6 new database tables** with ULID PKs and idempotent migrations:
  - `competitor_sources` — crawlable competitor feeds (RSS, sitemap, scrape, API)
  - `competitor_content_items` — crawled articles with dedup by content hash
  - `content_fingerprints` — morphic TF-IDF/keyword fingerprints for similarity
  - `differentiation_analyses` — LLM-assisted differentiation scoring
  - `competitor_alerts` — configurable alert rules
  - `competitor_alert_events` — fired alert history with notification log

#### Crawler Infrastructure (Chunk 1)
- `CrawlerService` — orchestrates RSS, sitemap, scrape, and API crawlers
- `RssCrawler`, `SitemapCrawler`, `ScrapeCrawler`, `ApiCrawler` — pluggable crawlers
- `CrawlCompetitorSourceJob` — queued job with retries + stale-check

#### Fingerprinting & Similarity (Chunk 2-3)
- `ContentFingerprintService` — TF-IDF vectorization over content body
- `SimilarityCalculator` — cosine similarity between fingerprint vectors
- `SimilarContentFinder` — finds the top-N most similar competitor items

#### Differentiation Analysis Engine (Chunk 4)
- `DifferentiationAnalysisService` — LLM-powered angle/gap/recommendation extraction
- `DifferentiationResult` — typed value object for analysis output
- Pipeline stage `CompetitorAnalysisStage` — integrates into the content pipeline

#### Pipeline Integration (Chunk 5)
- `CompetitorAnalysisStage` wired into `StageRegistry`
- Automatic enrichment of `ContentBrief` with competitor insights on pipeline run

#### Alert System (Chunk 6)
- `CompetitorAlertService` — evaluates active alerts against new competitor content
- `CheckCompetitorAlertsJob` — queued job dispatched post-crawl
- `CompetitorAlertNotification` — Laravel notification (email channel)
- `SlackChannel` — Block Kit Slack webhook notifications
- `WebhookChannel` — generic HTTP webhook with structured JSON payload
- Alert types: `new_content`, `keyword`, `high_similarity`

#### Knowledge Graph Integration (Chunk 7)
- `CompetitorGraphIndexer` — creates virtual nodes + `competitor_similarity` edges
- Reuses existing `content_graph_nodes` / `content_graph_edges` tables from #14
- Competitor items indexed with deterministic node IDs (SHA-1 prefix)

#### REST API (Chunk 8)
- `CompetitorSourceController` — CRUD for competitor sources
- `CompetitorController` — content listing, crawl trigger, alert CRUD
- `DifferentiationController` — analysis listing + summary endpoint
- Form requests with full validation
- JSON:API-style resources

#### Security Hardening (Chunk 9)
- Input validation on all competitor source URLs (must match protocol/domain whitelist)
- Rate limiting on crawlers (500 req/day per source)
- Auth: All endpoints require `manage-competitors` permission
- CORS disabled for competitor data (internal only)
- All components use Composition API + TypeScript

#### Monitoring & Retention (Chunk 10)
- `CrawlerHealthMonitor` — detects stale/high-error sources, logs warnings
- `RetentionPolicyService` — prunes old content/analyses/events on configurable schedule
- Scheduler entries: health check (hourly), retention prune (weekly Sun 02:00)
- OpenAPI 3.1 spec: `docs/competitor-differentiation-api.yaml`
- Blog post: `docs/blog-competitor-differentiation.md`

### Configuration
```env
COMPETITOR_ANALYSIS_ENABLED=true
COMPETITOR_SIMILARITY_THRESHOLD=0.25
COMPETITOR_MAX_ANALYZE=5
COMPETITOR_AUTO_ENRICH_BRIEFS=true
COMPETITOR_CONTENT_RETENTION_DAYS=90
COMPETITOR_ANALYSIS_RETENTION_DAYS=180
COMPETITOR_ALERT_EVENT_RETENTION_DAYS=30
```

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
