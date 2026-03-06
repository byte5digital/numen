# Changelog

All notable changes to Numen will be documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

**Pre-1.0 note:** Breaking changes can occur in any `0.x.0` minor bump. They'll always be documented here. See the [Architecture Review](docs/ARCHITECTURE_REVIEW_V1.md) for the versioning policy and roadmap to 1.0.

---

## [Unreleased]

### Planned for 0.2.0
- Remove legacy `numen.anthropic` config block (duplicates `numen.providers.anthropic`)
- GitHub Actions CI: PHPUnit, Pint, Larastan
- `AgentContract` interface extracted from `Agent` abstract class

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
- Content management (list, view, approve pipeline runs)
- Brief creation with keyword and priority controls
- Pipeline run monitoring per content piece
- Persona management
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

[Unreleased]: https://github.com/byte5labs/numen/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/byte5labs/numen/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/byte5labs/numen/releases/tag/v0.1.0
