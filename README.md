# Numen

> *Content, animated by AI.*

[![CI](https://github.com/byte5digital/numen/actions/workflows/ci.yml/badge.svg)](https://github.com/byte5digital/numen/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com)
[![Version](https://img.shields.io/badge/version-0.1.1-green.svg)](CHANGELOG.md)

**AI-first headless CMS. You write briefs. AI writes content.**

Instead of opening a text editor, you describe what you need. A pipeline of AI agents handles generation, SEO optimization, and quality review — then publishes automatically (or waits for human sign-off if you want it). Every frontend gets a clean REST API.

---

## How It Works

Traditional CMS: human opens editor → writes content → hits publish.

Numen: you submit a brief → pipeline runs in the background → content appears.

```
Brief
  └─► Content Creator (claude-sonnet-4-6)   — writes the article
        └─► SEO Expert (claude-haiku-4-5)   — optimizes metadata & keywords
              └─► Editorial Director (claude-opus-4-6) — quality gate (score ≥ 80 → publish)
```

Each stage is a queued job. The pipeline is event-driven. You can plug in a human review gate anywhere.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Admin UI                             │
│              Inertia.js + Vue 3 (SPA)                       │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                    Laravel 12 App                           │
│                                                             │
│  ┌──────────────┐   ┌──────────────┐   ┌─────────────────┐ │
│  │  REST API    │   │   Pipeline   │   │   AI Agents     │ │
│  │  /api/v1/*  │   │   Engine     │   │  + LLM Manager  │ │
│  └──────────────┘   └──────┬───────┘   └────────┬────────┘ │
│                            │                    │           │
│                    ┌───────▼──────┐   ┌─────────▼────────┐ │
│                    │  Queue Jobs  │   │  Provider Layer  │ │
│                    │  (Redis)     │   │  Anthropic       │ │
│                    └──────────────┘   │  OpenAI          │ │
│                                       │  Azure OpenAI    │ │
│  ┌──────────────────────────────┐     └──────────────────┘ │
│  │  SQLite (dev) / MySQL (prod) │                           │
│  └──────────────────────────────┘                           │
└─────────────────────────────────────────────────────────────┘
```

**Key design decisions:**

- **Provider abstraction** — swap Anthropic ↔ OpenAI ↔ Azure without touching pipeline code. Fallback chain auto-retries on rate limits.
- **Pipeline-as-config** — stages defined in DB, not hardcoded. Add/remove stages without deploying code.
- **Block-based content** — every piece of content is a collection of typed `ContentBlock` records. Flexible for headless delivery.
- **Full provenance** — every AI call logged (`AIGenerationLog`) with model, tokens, cost, and which pipeline stage triggered it.

---

## Stack

| Layer | Tech |
|---|---|
| Backend | PHP 8.4, Laravel 12 |
| Frontend (admin) | Inertia.js, Vue 3 |
| Auth | Laravel Sanctum (API tokens) |
| Queue | Redis (or database for dev) |
| Database | SQLite (dev), MySQL (prod) |
| AI providers | Anthropic, OpenAI, Azure OpenAI |

---

## Quick Start

You need PHP 8.4, Composer, Node.js 18+, and Redis (or switch to `QUEUE_CONNECTION=database`). An Anthropic API key is required minimum — OpenAI and Azure are optional.

```bash
# 1. Clone
git clone https://github.com/byte5labs/numen.git
cd numen

# 2. Install dependencies
composer install
npm install

# 3. Configure
cp .env.example .env
php artisan key:generate
```

Open `.env` and set your API key:

```env
ANTHROPIC_API_KEY=sk-ant-your-key-here
```

```bash
# 4. Database
touch database/database.sqlite
php artisan migrate

# 5. Seed demo data (creates a Space, Personas, and a pipeline)
php artisan db:seed --class=DemoSeeder

# 6. Start everything (three terminals)
php artisan serve          # → http://localhost:8000
php artisan queue:work     # processes pipeline jobs
npm run dev                # Vite (admin UI hot-reload)
```

That's it. Hit `http://localhost:8000` for the admin UI.

**For dev without Redis**, add `QUEUE_CONNECTION=database` to `.env` and run `php artisan queue:table && php artisan migrate` first.

---

## Create Your First Content

Get an API token from the admin UI (Settings → API Tokens), then:

```bash
# Submit a brief — this kicks off the full pipeline
curl -X POST http://localhost:8000/api/v1/briefs \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "space_id": "YOUR_SPACE_ID",
    "title": "10 Reasons to Use a Headless CMS in 2026",
    "description": "A developer-focused blog post covering performance, flexibility, and DX benefits of headless architecture",
    "content_type_slug": "blog_post",
    "target_keywords": ["headless cms", "jamstack", "api-first"],
    "priority": "high"
  }'

# → Returns a pipeline_run_id. Poll it for status:
curl http://localhost:8000/api/v1/pipeline-runs/PIPELINE_RUN_ID \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Watch the queue worker terminal — you'll see each stage fire in sequence. Content is auto-published if the Editorial Director scores it ≥ 80.

---

## API Reference

### Authentication

Public read endpoints require no auth. Management endpoints use Sanctum bearer tokens.

```bash
-H "Authorization: Bearer YOUR_TOKEN"
```

The full OpenAPI 3.1.0 specification is available at `GET /api/documentation`.

**Rate limits (public endpoints):** 60 requests/minute for content and pages; 30 requests/minute for component types.

### Content Delivery (public)

```bash
# List published content
curl http://localhost:8000/api/v1/content

# Get by slug
curl http://localhost:8000/api/v1/content/my-article-slug

# Filter by content type
curl http://localhost:8000/api/v1/content/type/blog_post

# List pages
curl http://localhost:8000/api/v1/pages

# Get page by slug
curl http://localhost:8000/api/v1/pages/about
```

### Content Management (authenticated)

```bash
# Create a brief (starts the pipeline)
curl -X POST http://localhost:8000/api/v1/briefs \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"space_id":"...","title":"...","description":"...","content_type_slug":"blog_post"}'

# List briefs
curl http://localhost:8000/api/v1/briefs \
  -H "Authorization: Bearer TOKEN"

# Check pipeline status
curl http://localhost:8000/api/v1/pipeline-runs/RUN_ID \
  -H "Authorization: Bearer TOKEN"

# Approve a human-gate stage
curl -X POST http://localhost:8000/api/v1/pipeline-runs/RUN_ID/approve \
  -H "Authorization: Bearer TOKEN"

# List active personas
curl http://localhost:8000/api/v1/personas \
  -H "Authorization: Bearer TOKEN"

# Cost analytics (last 100 day-model-purpose groups)
curl http://localhost:8000/api/v1/analytics/costs \
  -H "Authorization: Bearer TOKEN"
```

### Component Types (headless page builder)

```bash
# List registered component types
curl http://localhost:8000/api/v1/component-types

# Get specific type schema
curl http://localhost:8000/api/v1/component-types/hero_banner
```

---

## Model Allocation

Each pipeline stage uses the right model for the job — balance cost vs. capability.

| Stage | Default Model | Role | Est. Cost/Article |
|---|---|---|---|
| Content Generation | `claude-sonnet-4-6` | Full article writing | ~$0.05–0.15 |
| SEO Optimization | `claude-haiku-4-5` | Meta, keywords, slug | ~$0.005–0.02 |
| Editorial Review | `claude-opus-4-6` | Quality scoring & feedback | ~$0.10–0.30 |
| Planning / Strategy | `claude-opus-4-6` | Brief analysis, outlines | ~$0.05–0.15 |
| Classification | `claude-haiku-4-5` | Tagging, categorization | ~$0.001–0.005 |
| Premium Generation | `claude-opus-4-6` | High-stakes content | ~$0.20–0.50 |

**All model assignments are configurable via env vars** — see Configuration below. You can route any role to OpenAI or Azure with `AI_MODEL_GENERATION=openai:gpt-4o`.

Rough running cost: **~$25/month for 100 articles** with the default setup.

---

## Configuration

Copy `.env.example` to `.env`. Required variables are marked **required**.

### Core

| Variable | Default | Notes |
|---|---|---|
| `APP_KEY` | — | **Required.** Set by `php artisan key:generate` |
| `APP_URL` | `http://localhost:8000` | Public URL of your installation |
| `DB_CONNECTION` | `sqlite` | `sqlite` or `mysql` |
| `DB_DATABASE` | `database/database.sqlite` | Path for SQLite, DB name for MySQL |
| `QUEUE_CONNECTION` | `redis` | `redis` (prod) or `database` (dev) |

### AI Providers

At least one API key is required.

| Variable | Default | Notes |
|---|---|---|
| `AI_DEFAULT_PROVIDER` | `anthropic` | `anthropic`, `openai`, or `azure` |
| `AI_FALLBACK_CHAIN` | `anthropic,openai,azure` | Comma-separated, tried in order on failure |
| `ANTHROPIC_API_KEY` | — | Get from console.anthropic.com |
| `OPENAI_API_KEY` | — | Optional, enables OpenAI fallback |
| `AZURE_OPENAI_API_KEY` | — | Optional, enables Azure fallback |
| `AZURE_OPENAI_ENDPOINT` | — | Required if using Azure |

### Model Assignments

Override which model handles each role. Format: `model-name` or `provider:model-name`.

| Variable | Default | Example Override |
|---|---|---|
| `AI_MODEL_GENERATION` | `claude-sonnet-4-6` | `openai:gpt-4o` |
| `AI_MODEL_GENERATION_PREMIUM` | `claude-opus-4-6` | `anthropic:claude-opus-4-6` |
| `AI_MODEL_SEO` | `claude-haiku-4-5-20251001` | `openai:gpt-4o-mini` |
| `AI_MODEL_REVIEW` | `claude-opus-4-6` | `openai:gpt-4o` |
| `AI_MODEL_PLANNING` | `claude-opus-4-6` | `claude-sonnet-4-6` |
| `AI_MODEL_CLASSIFICATION` | `claude-haiku-4-5-20251001` | `openai:gpt-4o-mini` |

### Cost Controls

| Variable | Default | Notes |
|---|---|---|
| `AI_COST_DAILY_LIMIT` | `50` | USD. Pipeline stops when exceeded |
| `AI_COST_MONTHLY_LIMIT` | `500` | USD |
| `AI_COST_PER_CONTENT_LIMIT` | `2` | USD per content piece |

### Pipeline Behavior

| Variable | Default | Notes |
|---|---|---|
| `AI_AUTO_PUBLISH_SCORE` | `80` | Editorial score (0–100) required for auto-publish |
| `AI_HUMAN_GATE_TIMEOUT` | `48` | Hours before a paused pipeline times out |
| `AI_CONTENT_REFRESH_DAYS` | `30` | Days before content is eligible for AI refresh |

---

## Project Structure

```
app/
├── Agents/                  # AI agent system
│   ├── Agent.php            # Abstract base (extend for custom agents)
│   ├── AgentFactory.php     # Resolves agent type → implementation
│   └── Types/
│       ├── ContentCreatorAgent.php
│       ├── SeoExpertAgent.php
│       └── EditorialDirectorAgent.php
├── Pipelines/
│   └── PipelineExecutor.php # Orchestrates stage execution
├── Services/AI/
│   ├── LLMManager.php       # Provider routing + fallback chain
│   ├── CostTracker.php      # Per-call cost accounting
│   └── Providers/           # AnthropicProvider, OpenAIProvider, AzureOpenAIProvider
├── Models/                  # 16 Eloquent models
│   ├── Content.php / ContentBlock.php / ContentVersion.php
│   ├── ContentBrief.php / ContentPipeline.php / ContentType.php
│   ├── Persona.php / Space.php / Page.php / PageComponent.php
│   ├── ComponentDefinition.php
│   ├── AIGenerationLog.php / PipelineRun.php
│   └── MediaAsset.php / Setting.php / User.php
├── Jobs/                    # Queue jobs for pipeline stages
├── Events/                  # PipelineStarted, StageCompleted, etc.
└── Http/Controllers/Api/    # Content delivery + management controllers
config/
└── numen.php               # All Numen config (providers, models, pipeline, costs)
```

---

## Contributing

Contributions are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a PR.

- Found a bug? [Open an issue](https://github.com/byte5labs/numen/issues)
- Want to add a feature? Check existing issues first, then open a discussion
- First contribution? Look for issues tagged `good first issue`

---

## Roadmap

See [CHANGELOG.md](CHANGELOG.md) for what's in each release.

**Near-term (0.2.0):**
- Deduplicate config `numen.anthropic` block (duplicates `numen.providers.anthropic`)
- GitHub Actions CI (PHPUnit + Pint + Larastan)
- `AgentContract` interface extracted from `Agent` abstract class

**Medium-term (0.3.0):**
- Extract formal interfaces (`AgentContract`, `PipelineExecutorContract`)
- Docker / docker-compose setup

---

## License

MIT — see [LICENSE](LICENSE).

Built by [byte5.labs](https://byte5.de).
