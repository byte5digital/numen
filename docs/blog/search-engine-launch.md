# Numen Now Has AI-Powered Search — And It's Not Just Keyword Matching

*Published: March 7, 2026 | byte5.labs*

---

We just shipped something we're genuinely excited about: a three-tier AI-powered search engine built directly into Numen. Not a bolt-on. Not a third-party embed. A native, deeply integrated search system that understands your content the way your users do.

## The Problem With "Search"

Most CMS search is basically `WHERE title LIKE '%query%'`. It works if your user types exactly the right word. It fails the moment they paraphrase, use a synonym, or ask a natural question.

We've been thinking about this for a while. The answer isn't just better keyword matching — it's search that operates at multiple levels of understanding.

## Three Tiers, One Endpoint

Numen Search runs three tiers simultaneously and merges the results:

### Tier 1: Instant Search (Meilisearch)
Sub-50ms keyword search with typo tolerance, synonyms, and highlighting. This is your classic fast search — the one that makes your search bar feel snappy. Powered by Meilisearch via Laravel Scout.

### Tier 2: Semantic Search (pgvector)
This is where it gets interesting. Every piece of content you publish gets embedded into a 1536-dimensional vector using OpenAI's `text-embedding-3-small`. When a user searches, their query gets embedded too, and we find content by *meaning*, not just keyword overlap.

A user searching for "how to get started" will find your "Installation & Setup Guide" even if the words don't match. Because the intent does.

### Tier 3: Conversational Search — "Ask"
The third tier is something different entirely. Users can ask full questions in natural language and get a direct answer, grounded in your published content.

It works like this:
1. The question is embedded and semantically matched against content chunks
2. The top chunks are assembled into a grounded context
3. Claude (Haiku) generates a direct answer, citing sources with `[1]`, `[2]` etc.
4. Citations are extracted and returned alongside the answer

This is RAG (Retrieval-Augmented Generation) — but constrained entirely to your content. The LLM cannot go outside it. Every answer cites a source. Conversations persist for 24 hours.

## Graceful Degradation — Always On

One design principle we held firm on: search should *never fail silently*. If Meilisearch is down, we fall back to semantic. If pgvector isn't available (say, you're on SQLite in dev), we fall back to a SQL `LIKE` query.

Every tier degrades to the next. The experience may be less precise, but it never breaks.

## What Admins Get

Beyond the search API itself, there's a full admin layer:

- **Synonyms** — define bidirectional or one-way synonym sets, synced to Meilisearch automatically
- **Promoted Results** — pin specific content to appear first for certain queries, with optional date ranges
- **Analytics** — query volume, click-through rates, zero-result queries, content gaps
- **Index Health** — see embedding counts, capability status, last reindex time
- **Reindex** — trigger a full content reindex with a 5-minute cooldown to prevent abuse

## Security First

We didn't ship this without a thorough security review. A few things we're particularly glad we caught:

- **LIKE injection** — user search terms are now properly escaped before LIKE pattern matching
- **Admin authorization** — all `/admin/search` routes require the admin role, not just authentication
- **Prompt injection** — the conversational driver sanitizes questions, uses strict system prompt rules, and validates output for leaked instructions
- **Rate limiting** — 60/min for search, 10/min for Ask (LLM calls are expensive), 30/min for click tracking

## The API

Everything is accessible via the REST API:

```
GET  /api/v1/search?q=your+query&mode=hybrid
GET  /api/v1/search/suggest?q=ins
POST /api/v1/search/ask
     { "question": "How do I configure webhooks?" }
POST /api/v1/search/click
     { "query": "webhooks", "content_id": "...", "position": 1 }
```

The `mode` parameter lets you force a specific tier: `instant`, `semantic`, or `hybrid` (default, merges both via Reciprocal Rank Fusion).

## 120 Tests

The search implementation ships with 9 test files and 120 test cases covering every tier, every fallback path, every admin endpoint, and every security fix. We take quality gates seriously — PHPStan L5, Laravel Pint, and a full test suite all had to be green before this merged.

## What's Next

This is v1 of search. On our radar:

- Frontend search widget (Vue component) ready to drop into any headless theme
- Search analytics-driven content recommendations
- Auto-synonym suggestions from zero-result queries
- Multi-language embedding support

---

Numen is available now. The search feature is live on the `main` branch.

→ [View the source on GitHub](https://github.com/byte5digital/numen)
→ [Read the full API documentation](/docs/features/search)
→ [Discussion #17 — AI-Powered Search Engine](https://github.com/byte5digital/numen/discussions/17)

---

*Built by byte5.labs — AI-first software, shipped with care.*
