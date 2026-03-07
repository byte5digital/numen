# Search Architecture — AI-Powered Search Engine

> Discussion #17 | Refined architecture based on product discussion (2026-03-07)

## Overview

Three-tier search engine with Content Gap Engine and configurable Search Assistant Persona.

## Tier 1: Instant Search (Keyword)

- **Backend:** Meilisearch Cloud (Frankfurt, free tier)
- **Integration:** Laravel Scout with Meilisearch driver
- Full-text index of all content: titles, body, blocks, taxonomy terms, meta descriptions
- Typo tolerance, faceted filtering, highlighting, autocomplete
- API: `GET /api/v1/search?q=...&type=...&tag=...`

## Tier 2: Semantic Search

- Vector embeddings for all content (generated on publish)
- **Storage:** Meilisearch hybrid search (v1.12+ built-in vector search) — eliminates need for separate pgvector
- Hybrid ranking: keyword score + semantic similarity
- Cross-language search (embeddings are language-agnostic)
- API: `GET /api/v1/search?q=...&mode=semantic`

## Tier 3: Conversational Search (Grounded RAG)

- "Ask Numen" — natural language Q&A over published content
- **Strictly grounded** — only answers from YOUR content, never generic AI
- Inline citations linking back to source articles

### Grounding Rules

- Minimum similarity threshold required before generating an answer
- Source attribution mandatory on every response
- System prompt scoped: "Answer only from provided context. If context doesn't contain the answer, say so."
- No fallback to general LLM knowledge
- Partial match: answer what's available, clearly state gaps
- No match: "I don't have content about this topic yet" + suggest related content

### API

- `POST /api/v1/search/ask { "question": "..." }`

## Search Assistant Persona

The conversational search layer is powered by a **configurable persona** — same architecture as content personas.

### Configurable Properties

| Property | Description | Example |
|---|---|---|
| Name | Display name | "Ask Numen", "Chef's Assistant", "Legal Guide" |
| Tone & Voice | Communication style | Formal, casual, technical, friendly |
| Domain Scope | What topics to cover vs decline | "I only cover recipes, not nutrition advice" |
| Response Style | Answer format preferences | Concise, detailed, bullets vs prose, always cite sources |
| Greeting | Welcome/intro message | Customizable per brand |
| No-Result Message | What to say when no content matches | Customizable empty state |
| LLM Provider + Model | AI backend | Same provider/model config as other personas |
| System Prompt | Core RAG instruction | Editable by admin |
| Guardrails | Hard boundaries | Never give medical/legal advice, stay on topic |

### Implementation

- Extends existing `personas` table — new persona `type: 'search_assistant'`
- One active search assistant persona per site (configurable in admin)
- Same CRUD interface as content personas
- System prompt template with RAG context injection

## Content Gap Engine

Turn search misses into content creation opportunities.

### The Feedback Loop

```
User asks question → No content match
        ↓
   Log to search_gaps table
        ↓
   Cluster similar queries (daily scheduled job)
        ↓
   Threshold reached (configurable, e.g., 3+ similar queries)
        ↓
   Auto-generate content brief suggestion
        ↓
   → Human review queue (Admin UI)
        ↓
   Editor approves / edits / rejects
        ↓
   Approved → enters AI content pipeline
   (Brief → Generate → Illustrate → SEO → Review → Publish)
        ↓
   Next time someone asks → answer exists ✅
```

### Human-in-the-Loop (Mandatory)

- AI suggests brief → **human approves, edits, or rejects**
- Human configures query threshold before suggestion triggers
- Human can mark queries as "not relevant" / "out of scope"
- AI **never** auto-creates content from gaps without editorial approval

### New Components

| Component | Type | Purpose |
|---|---|---|
| `SearchGapService` | Service | Logs zero/low-result queries with metadata |
| `GapClusteringJob` | Scheduled Job | Clusters similar search misses using embeddings |
| `ContentBriefSuggestion` | Model | Stores AI-generated brief proposals (pending/approved/rejected/published) |
| `GapDashboard` | Admin UI | Top unanswered queries, clusters, trends, brief queue |

### Admin UI — Search Analytics

- **Search Overview** — top queries, click-through rates, satisfaction
- **Content Gaps** tab — ranked topics users want but don't have
- **Suggested Briefs** queue — AI-drafted briefs awaiting editorial review
- **Lifecycle Tracking** — gap → brief → published content (full visibility)
- **Exclusions** — "Mark as out of scope" to permanently exclude topics

## Technical Architecture

```
┌─────────────┐    ┌──────────────┐    ┌─────────────────┐
│ Search API  │───▶│ SearchService│───▶│ Meilisearch     │ Tier 1+2
│ Controller  │    │              │    │ (keyword+vector) │
│             │    │              │───▶│ Grounded RAG    │ Tier 3
└─────────────┘    └──────────────┘    └─────────────────┘
                          │                     │
                   ┌──────┴──────┐    ┌─────────┴─────────┐
                   │ Indexer     │    │ SearchGapService   │
                   │ (on publish)│    │ (on zero-result)   │
                   └─────────────┘    └─────────┬─────────┘
                                                │
                                      ┌─────────┴─────────┐
                                      │ GapClusteringJob   │
                                      │ (scheduled daily)  │
                                      └─────────┬─────────┘
                                                │
                                      ┌─────────┴─────────┐
                                      │ Brief Suggestion   │
                                      │ → Human Review     │
                                      │ → AI Pipeline      │
                                      └───────────────────┘
```

## Key Services

| Service | Responsibility |
|---|---|
| `SearchService` | Orchestrates all three tiers, routes queries |
| `ContentIndexer` | Queue job — indexes content on publish/update/delete |
| `EmbeddingService` | Generates vector embeddings via configured LLM provider |
| `RAGService` | Retrieval-augmented generation for conversational search |
| `SearchGapService` | Logs and analyzes zero-result queries |
| `SearchAnalyticsService` | Tracks queries, clicks, satisfaction, zero-results |

## Infrastructure

- **Meilisearch Cloud** (free tier, Frankfurt) — keyword + hybrid vector search
- **No additional services needed** — uses existing LLM providers, existing AI pipeline, existing database
- Marcel creates Meilisearch Cloud account when feature is ready for integration

## Search Backend Decision

**Meilisearch Cloud** chosen over alternatives:
- ✅ Best DX, first-class Laravel Scout support
- ✅ Built-in hybrid search (keyword + vector) since v1.12
- ✅ Free tier (100k docs) — plenty for early stages
- ✅ EU region (Frankfurt) for data residency
- ✅ Sub-50ms responses, typo tolerance, facets — great for demos with small content
- ❌ Algolia: overkill pricing, heavier vendor lock-in
- ❌ Typesense: solid but weaker Laravel ecosystem integration
- ❌ Self-hosted: unnecessary ops burden at this stage
- ❌ pgvector standalone: Meilisearch hybrid covers this natively

## Differentiator

> "Your users tell you what to write next."

No other CMS closes this loop natively:
- Search informs creation → creation improves search
- Content strategy driven by real user demand, not guesswork
- Every Numen deployment gets a brandable search assistant, not a generic chatbot

---

*Architecture by Main 🧠 + Marcel | 2026-03-07*
