# Numen

> *Content, animated by AI.*

[![CI](https://github.com/byte5digital/numen/actions/workflows/ci.yml/badge.svg)](https://github.com/byte5digital/numen/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com)
[![Version](https://img.shields.io/badge/version-0.2.1-green.svg)](CHANGELOG.md)

**AI-first headless CMS. You write briefs. AI writes content.**

Instead of opening a text editor, you describe what you need. A pipeline of AI agents handles generation, SEO optimization, and quality review — then publishes automatically (or waits for human sign-off if you want it). Every frontend gets a clean REST API.

---

## How It Works

Traditional CMS: human opens editor → writes content → hits publish.

Numen: you submit a brief → pipeline runs in the background → content appears.

```
Brief
  └─► Content Creator (claude-sonnet-4-6)     — writes the article
        └─► AI Illustrator (multi-provider)   — generates hero image from content
              └─► SEO Expert (claude-haiku-4-5-20251001)   — optimizes metadata & keywords
                    └─► Editorial Director (claude-opus-4-6) — quality gate (score ≥ 80 → publish)
                          └─► Auto-Publish              — goes live automatically
```

Each stage is a queued job. The pipeline is event-driven. Stages are defined in DB — add, remove, or reorder without deploying code. You can plug in a `human_gate` stage anywhere to pause for manual review.

---

---

## Features

### Content Generation Pipeline
Submit a brief → AI agents generate, illustrate, optimize, and quality-gate content → auto-publish or human review.

### AI Content Repurposing Engine
**New in v0.8.0.** One-click content repurposing to 8 formats:
- **Twitter thread**, LinkedIn post, Newsletter section, Instagram caption
- **Podcast script outline**, Product page copy, FAQ section, YouTube description
- Tone-aware & brand-consistent via Persona/LLM system
- Batch repurposing (50 items) + cost estimation
- Staleness detection (auto-repurpose when source updates)
- Custom format templates per space

### Multi-Provider AI
Swap between Anthropic, OpenAI, Azure OpenAI, Together AI — no code changes. Fallback chain auto-retries on rate limits.

### Multi-Provider Image Generation
OpenAI, Together AI, fal.ai, Replicate — choose the best model for your brand. Images auto-download and link to content.

### RBAC with AI Governance
Role-based access control (Admin, Editor, Author, Viewer) with space-scoped permissions, AI budget limits, and immutable audit logs.

### CLI for Automation
8 commands for content, briefs, and pipeline management — perfect for CI/CD hooks and server-side workflows.


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
│                                       │  Together AI     │ │
│                                       │  fal.ai          │ │
│                                       │  Replicate       │ │
│  ┌──────────────┐  ┌───────────────┐  └──────────────────┘ │
│  │  SQLite/MySQL│  │  Media Assets │                        │
│  │  (content DB)│  │  (AI images)  │                        │
│  └──────────────┘  └───────────────┘                        │
└─────────────────────────────────────────────────────────────┘
```

**Key design decisions:**

- **Provider abstraction** — swap Anthropic ↔ OpenAI ↔ Azure without touching pipeline code. Fallback chain auto-retries on rate limits.
- **Multi-provider image generation** — Four image providers supported: OpenAI (GPT Image 1.5 / DALL-E 3), Together AI (FLUX models), fal.ai (FLUX / SD3.5 / Recraft), and Replicate (any model). An `ImagePromptBuilder` (powered by Haiku) crafts optimized prompts from content metadata; images are downloaded, stored as `MediaAsset` records, and attached to content. The active provider is configured per-persona via `generator_provider` / `generator_model`.
- **Media Library & Digital Asset Management** — Organize media assets into folders and collections with drag-and-drop upload. Automatic metadata extraction (MIME, dimensions, file size), optional AI auto-tagging via Claude vision, and image editing (crop/rotate/resize). Automatic variant generation (thumbnail, medium, large) for web delivery. Public headless API (`/v1/public/media`) for CDN edge caching; full REST API with Sanctum auth. MediaPicker Vue component for seamless integration with content editor. Usage tracking prevents accidental deletion of in-use assets.
- **RBAC with AI governance** — role-based access control (Admin, Editor, Author, Viewer) with space-scoped permissions, AI budget limits per role, and immutable audit logs. Tokens inherit a subset of user permissions. No external dependencies — Numen's own implementation.
- **Pipeline-as-config** — stages defined in DB, not hardcoded. Add/remove/reorder stages without deploying code. Supports `human_gate` stages for manual review checkpoints.
- **Block-based content** — every piece of content is a collection of typed `ContentBlock` records. Flexible for headless delivery.
- **Full provenance** — every AI call logged (`AIGenerationLog`) with model, tokens, cost, and which pipeline stage triggered it. Image generation costs tracked per asset. Audit logs track all sensitive actions (publish, role assignment, AI generation, etc.).

---

## Stack

| Layer | Tech |
|---|---|
| Backend | PHP 8.4, Laravel 12 |
| Frontend (admin) | Inertia.js, Vue 3 |
| Auth | Laravel Sanctum (API tokens) |
| Queue | Redis (or database for dev) |
| Database | SQLite (dev), MySQL (prod) |
| AI providers (text) | Anthropic, OpenAI, Azure OpenAI |
| AI providers (image) | OpenAI, Together AI, fal.ai, Replicate |

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

## CLI Reference

Numen ships a full artisan-based CLI for server-side automation, scripted workflows, and CI/CD integration.

> **Security note:** The CLI runs with full application privileges. Restrict server access accordingly — CLI is not intended for untrusted users.

### Installation

The CLI is available automatically via artisan once the app is installed:

```bash
php artisan list numen
```

### Commands

#### `numen:status` — System Health Check

```bash
# Quick health overview
php artisan numen:status

# With AI provider model details
php artisan numen:status --details
```

Shows: database connectivity, content stats, cache, queue driver, and AI provider configuration.

---

#### `numen:content:list` — List Content

```bash
# List all content (latest 20)
php artisan numen:content:list

# Filter by type and status
php artisan numen:content:list --type=blog_post --status=published --limit=50
```

---

#### `numen:content:import` — Bulk Import from JSON

```bash
# Import from file
php artisan numen:content:import --file=storage/exports/content.json

# Preview without writing (dry run)
php artisan numen:content:import --file=storage/exports/content.json --dry-run

# Import into specific space
php artisan numen:content:import --file=content.json --space-id=<uuid>
```

**Import file format (JSON array):**

```json
[
  {
    "slug": "my-first-article",
    "title": "My First Article",
    "content_type": "blog_post",
    "status": "draft",
    "locale": "en",
    "excerpt": "A short summary of the article.",
    "body": "Full article body in HTML or Markdown.",
    "seo_data": {
      "meta_title": "My First Article | Numen",
      "meta_description": "A short summary."
    }
  }
]
```

Valid `status` values: `draft`, `published`, `archived` (invalid values default to `draft`).

---

#### `numen:content:export` — Export to JSON or Markdown

```bash
# Export all content to storage/exports/ (default)
php artisan numen:content:export

# Export as Markdown to a specific file
php artisan numen:content:export --format=markdown --output=/tmp/export.md

# Filter by type and status
php artisan numen:content:export --type=blog_post --status=published

# Export a single item by ID
php artisan numen:content:export --id=<uuid>
```

When `--output` is omitted, exports default to `storage/exports/<timestamp>.json`.

---

#### `numen:brief:create` — Create a Content Brief

```bash
# Create a brief and trigger the pipeline
php artisan numen:brief:create --title="10 Tips for Laravel Performance"

# Full options
php artisan numen:brief:create \
  --title="SEO Guide 2026" \
  --type=guide \
  --persona=seo-expert \
  --priority=high \
  --keywords=laravel --keywords=performance \
  --description="Comprehensive SEO guide for developers"

# Create without running the pipeline
php artisan numen:brief:create --title="Draft Idea" --no-run
```

Valid `--priority` values: `low`, `normal`, `high`, `urgent` (invalid values default to `normal`).

---

#### `numen:brief:list` — List Briefs

```bash
# List latest 20 briefs
php artisan numen:brief:list

# Filter by status and space
php artisan numen:brief:list --status=pending --limit=50
```

---

#### `numen:pipeline:run` — Trigger Pipeline Run

```bash
# Run the active pipeline for a brief
php artisan numen:pipeline:run --brief-id=<uuid>

# Run a specific pipeline
php artisan numen:pipeline:run --brief-id=<uuid> --pipeline-id=<uuid>
```

---

#### `numen:pipeline:status` — Check Pipeline Status

```bash
# Show recent pipeline runs
php artisan numen:pipeline:status

# Show only running pipelines
php artisan numen:pipeline:status --running

# Show more history
php artisan numen:pipeline:status --limit=50
```

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

# Filter by taxonomy
curl "http://localhost:8000/api/v1/content?taxonomy[categories]=laravel&taxonomy[tags]=tutorial"

# List pages
curl http://localhost:8000/api/v1/pages

# Get page by slug
curl http://localhost:8000/api/v1/pages/about

# List taxonomy vocabularies
curl http://localhost:8000/api/v1/taxonomies

# Browse term tree
curl http://localhost:8000/api/v1/taxonomies/categories

# Content by term
curl http://localhost:8000/api/v1/taxonomies/categories/terms/laravel/content
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


### Webhooks

Webhooks enable real-time event delivery to external systems. Subscribe to events like content publication, pipeline completion, or media uploads, and receive signed HTTP POST requests when they occur.

#### Overview

A webhook is a URL endpoint + event filter you configure in Numen. When a subscribed event fires, Numen serializes it into a JSON payload, signs it with HMAC-SHA256, and delivers it asynchronously to your endpoint with automatic retries.

**Key features:**
- **Event subscriptions** — Choose which events trigger deliveries (e.g., `content.published`, `pipeline.completed`, or `*` for all)
- **HMAC-SHA256 signing** — Every delivery includes an `X-Numen-Signature` header. Verify it consumer-side to ensure authenticity.
- **Secure secrets** — Webhook secrets are encrypted at rest; never logged or exposed in API responses.
- **Audit trail** — Every delivery (attempt, success, failure) is recorded and queryable.
- **Automatic retries** — Failed deliveries retry with exponential backoff (3 attempts, 60+ seconds apart).
- **Rate limiting** — 60 webhook creations/minute per space; 10 manual redelivery attempts/minute per webhook.
- **SSRF protection** — URLs are validated to prevent Server-Side Request Forgery (private IP ranges blocked).

#### Event Catalog

Subscribe to one or more events. Wildcards supported: `content.*`, `pipeline.*`, `media.*`, `user.*`, or `*` (all events).

| Event | Payload Contains | Fired When |
|---|---|---|
| `content.published` | content_id, space_id, title, content_type, published_at, version | Content item published (either directly or after pipeline approval) |
| `content.updated` | content_id, space_id, title, content_type, version | Content metadata updated (title, type, tags, etc.) |
| `content.deleted` | content_id, space_id | Content permanently deleted |
| `pipeline.started` | pipeline_id, space_id, run_id | New pipeline run initiated |
| `pipeline.completed` | pipeline_id, space_id, run_id, ai_score, completed_at | Pipeline run finished successfully |
| `pipeline.failed` | pipeline_id, space_id, run_id, stage, completed_at | Pipeline run failed at a stage |
| `media.uploaded` | asset_id, space_id, filename, mime_type, url | Media asset uploaded |
| `media.processed` | asset_id, space_id, filename, mime_type, url | Media processed (image generated, etc.) |
| `media.deleted` | asset_id, space_id, filename | Media asset deleted |
| `user.created` | user_id, space_id | User added to space |
| `user.updated` | user_id, space_id | User permissions changed |
| `user.deleted` | user_id, space_id | User removed from space |

#### Webhook Payload Format

Every webhook POST includes:

```json
{
  "id": "evt_abc123def456",
  "event": "content.published",
  "timestamp": "2026-03-15T11:30:45.000Z",
  "data": {
    "content_id": "ctn_xyz789",
    "space_id": "spc_001",
    "title": "My First Article",
    "content_type": "blog_post",
    "published_at": "2026-03-15T11:30:00.000Z",
    "version": 2
  }
}
```

**Headers included in every delivery:**
- `Content-Type: application/json`
- `X-Numen-Event: content.published` (event type)
- `X-Numen-Delivery: <deliveryId>` (delivery record ID for audit trail)
- `X-Numen-Signature: sha256=<hmac>` (HMAC-SHA256 signature)
- Any custom headers you configure on the webhook

#### HMAC Signature Verification

Verify every incoming webhook by recomputing the HMAC and comparing it to the header.

**PHP Example:**

```php
<?php
// Get the signature from headers
$signature = $_SERVER['HTTP_X_NUMEN_SIGNATURE'] ?? '';
// Get the raw request body (BEFORE json_decode!)
$body = file_get_contents('php://input');
// Get your webhook secret (stored securely)
$secret = env('NUMEN_WEBHOOK_SECRET');

// Compute expected signature
[$algo, $hash] = explode('=', $signature);
$computed = hash_hmac($algo, $body, $secret);

// Constant-time comparison to prevent timing attacks
if (!hash_equals($hash, $computed)) {
    http_response_code(401);
    die('Unauthorized: Invalid signature');
}

// Safe to process
$payload = json_decode($body, true);
echo "✓ Event received: {$payload['event']}\n";
```

**JavaScript (Node.js) Example:**

```javascript
import crypto from 'crypto';
import express from 'express';

const app = express();
const secret = process.env.NUMEN_WEBHOOK_SECRET;

app.post('/webhook', express.json({ verify: (req, res, buf) => {
  const signature = req.get('x-numen-signature');
  const [algo, hash] = signature.split('=');
  const computed = crypto
    .createHmac(algo, secret)
    .update(buf)
    .digest('hex');

  if (!crypto.timingSafeEqual(hash, computed)) {
    throw new Error('Unauthorized: Invalid signature');
  }
}}), (req, res) => {
  console.log(`✓ Event received: ${req.body.event}`);
  res.json({ success: true });
});

app.listen(3000);
```

#### Quick Setup Example

Create a webhook via API:

```bash
curl -X POST http://localhost:8000/api/v1/webhooks \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "space_id": "spc_001",
    "url": "https://my-app.example.com/webhooks/numen",
    "events": ["content.published", "pipeline.completed"],
    "is_active": true,
    "headers": {
      "X-Custom-Header": "my-value"
    }
  }'
```

Response:

```json
{
  "data": {
    "id": "wbk_abc123",
    "space_id": "spc_001",
    "url": "https://my-app.example.com/webhooks/numen",
    "events": ["content.published", "pipeline.completed"],
    "is_active": true,
    "secret": "wbk_secret_long_random_string_here",
    "created_at": "2026-03-15T11:00:00.000Z"
  }
}
```

**Store the secret securely** — you'll need it to verify incoming signatures. Never share or log it.

Listen at `https://my-app.example.com/webhooks/numen`:

```javascript
// Assume you already verified the signature (see above)
const payload = req.body;

switch (payload.event) {
  case 'content.published':
    console.log(`Article published: ${payload.data.title}`);
    // Sync to search index, email subscribers, etc.
    break;

  case 'pipeline.completed':
    console.log(`Pipeline ${payload.data.run_id} finished with score ${payload.data.ai_score}`);
    // Update dashboard, notify stakeholders, etc.
    break;
}

res.status(200).json({ received: true });
```

#### Rate Limits

| Action | Limit |
|---|---|
| Create/update webhook | 60/min per space |
| Redeliver webhook | 10/min per webhook |
| HTTP delivery timeout | 10 seconds per attempt |

Webhooks use standard HTTP 429 (Too Many Requests) when rate limits are exceeded. Check the `Retry-After` header for backoff.

#### Audit & Troubleshooting

List deliveries for a webhook:

```bash
curl http://localhost:8000/api/v1/webhooks/wbk_abc123/deliveries \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Response includes status, HTTP response code, and response body (first 4KB):

```json
{
  "data": [
    {
      "id": "dly_123",
      "webhook_id": "wbk_abc123",
      "event_id": "evt_abc123",
      "event_type": "content.published",
      "status": "delivered",
      "http_status": 200,
      "attempt_number": 1,
      "scheduled_at": "2026-03-15T11:05:00.000Z",
      "delivered_at": "2026-03-15T11:05:01.000Z"
    },
    {
      "id": "dly_456",
      "webhook_id": "wbk_abc123",
      "event_id": "evt_def456",
      "event_type": "pipeline.completed",
      "status": "abandoned",
      "http_status": null,
      "attempt_number": 3,
      "scheduled_at": "2026-03-15T11:06:00.000Z",
      "response_body": "Connection timeout"
    }
  ]
}
```

**Status values:**
- `pending` — Scheduled, waiting to be delivered
- `delivered` — Successfully delivered (HTTP 2xx)
- `abandoned` — Failed after 3 retry attempts; needs manual redeliver or debugging

Manually retry a failed delivery:

```bash
curl -X POST http://localhost:8000/api/v1/webhooks/wbk_abc123/deliveries/dly_456/redeliver \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---


---

## Model Allocation

Each pipeline stage uses the right model for the job — balance cost vs. capability.

| Stage | Default Model | Role | Est. Cost/Article |
|---|---|---|---|
| Content Generation | `claude-sonnet-4-6` | Full article writing | ~$0.05–0.15 |
| Image Prompt | `claude-haiku-4-5-20251001` | Crafts image prompts from content metadata | ~$0.001–0.005 |
| Image Generation | multi-provider | Hero image via OpenAI / Together AI / fal.ai / Replicate | ~$0.04–0.08 |
| SEO Optimization | `claude-haiku-4-5-20251001` | Meta, keywords, slug | ~$0.005–0.02 |
| Editorial Review | `claude-opus-4-6` | Quality scoring & feedback | ~$0.10–0.30 |
| Planning / Strategy | `claude-opus-4-6` | Brief analysis, outlines | ~$0.05–0.15 |
| Classification | `claude-haiku-4-5-20251001` | Tagging, categorization | ~$0.001–0.005 |
| Premium Generation | `claude-opus-4-6` | High-stakes content | ~$0.20–0.50 |

**All model assignments are configurable via env vars** — see Configuration below. You can route any role to OpenAI or Azure with `AI_MODEL_GENERATION=openai:gpt-4o`. Image generation supports four providers (OpenAI, Together AI, fal.ai, Replicate); the active provider is configured per-persona and gracefully skips if no key is configured.

Rough running cost: **~$35/month for 100 articles with images** with the default setup.

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

## Documentation

- **[Multi-Language & i18n Support](docs/features/i18n.md)** — content localization, AI-powered translation, locale management, and fallback chains
- **[Role-Based Access Control (RBAC)](docs/features/permissions.md)** — team access management, roles, permissions, space scoping, and audit logs
- **[RBAC Technical Guide](docs/RBAC_GUIDE.md)** — detailed API reference and implementation guide
- **[Permissions Architecture](docs/architecture/permissions-architecture.md)** — system design, data model, and security considerations
- **[OpenAPI Specification](openapi.yaml)** — full REST API reference with examples

---

## Contributing

Contributions are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a PR.

- Found a bug? [Open an issue](https://github.com/byte5labs/numen/issues)
- Want to add a feature? Check existing issues first, then open a discussion
- First contribution? Look for issues tagged `good first issue`

---

## Roadmap

See [CHANGELOG.md](CHANGELOG.md) for what's in each release.

**Shipped (post-0.1.1):**
- Larastan level 5 static analysis — 199 errors fixed, 0 remaining ✅
- Multi-provider image generation (OpenAI, Together AI, fal.ai, Replicate) ✅
- User management (CRUD) with admin frontend pages ✅
- Self-service password change ✅
- Permanent content deletion with cascade cleanup ✅
- Role-Based Access Control (RBAC) with space-scoped roles, AI budget governance, and audit logs ✅
- 134+ tests (up from 117 in 0.1.1) ✅
- **Taxonomy & Content Organization** — hierarchical vocabularies, drag-and-drop term trees, AI auto-categorization, full REST API ([docs](docs/features/taxonomy.md)) ✅
- **Multi-Language & i18n Support** — content localization, AI-powered translation, locale management, intelligent fallback chains, and translation workflow ([docs](docs/features/i18n.md)) ✅

**Near-term (0.2.0):**
- Deduplicate config `numen.anthropic` block (duplicates `numen.providers.anthropic`)
- `AgentContract` interface extracted from `Agent` abstract class

**Medium-term (0.3.0):**
- Extract formal interfaces (`AgentContract`, `PipelineExecutorContract`)
- Docker / docker-compose setup

---

## License

MIT — see [LICENSE](LICENSE).

Built by [byte5.labs](https://byte5.de).
