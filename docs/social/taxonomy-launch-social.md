# Taxonomy Launch Social Posts
_Numen v0.3.0 — AI-Powered Taxonomy & Content Organization_
_Prepared by Megaphone 📱 | 2026-03-07_

---

## 1. X/Twitter Thread

**Tweet 1 (hook)**
```
Every CMS ships categories and tags.

None of them categorize your content for you.

We just changed that. 🧵
```

---

**Tweet 2 (the AI angle)**
```
Numen v0.3.0 ships TaxonomyCategorizer — a pipeline stage that analyzes your content, matches it against YOUR vocabulary structure, and assigns terms automatically.

With confidence scores. With provenance tracking (human vs AI). Works on Anthropic, OpenAI, or Azure.

Not a plugin. Built in.
```

---

**Tweet 3 (code snippet)**
```
What it looks like on the API side:

POST /api/v1/content/42/terms
→ {"term_ids": [5, 12, 27]}

GET /api/v1/content/42/terms
→ [
    {"term": "Laravel", "vocabulary": "Topics",   "source": "ai",    "confidence": 0.94},
    {"term": "Intermediate", "vocabulary": "Audience", "source": "human", "confidence": null},
    {"term": "Tutorial",  "vocabulary": "Type",    "source": "ai",    "confidence": 0.87}
  ]

16 endpoints. Full headless control.
```

---

**Tweet 4 (the comparison)**
```
WordPress: register_taxonomy() + you write the rest
Strapi: rebuild category structures per project
Contentful: flat global tags, no hierarchy
Directus: custom junction tables, good luck

Numen: vocabulary trees, unlimited depth, drag-and-drop UI, AI assigns terms for you

Open source (MIT). Laravel 12 + Vue 3.
```

---

**Tweet 5 (CTA)**
```
332 tests, 752 assertions, all green.

No other open-source CMS — and frankly no commercial one we've checked — ships AI-native taxonomy.

Try it:

git clone https://github.com/byte5digital/numen
composer install && npm install
php artisan migrate

Then tell us what you build. 👇
```

---

## 2. LinkedIn Post

```
We just shipped something I haven't seen in any other CMS — open source or commercial.

**Numen v0.3.0 introduces AI-native taxonomy.** Not a plugin. Not a bolt-on. A first-class pipeline stage.

Here's what that means technically:

🗂️ **Vocabularies** — unlimited independent taxonomy namespaces per space (Topics, Audience, Content Type, Region — whatever your IA demands), each with its own term tree and API surface

🌳 **Hierarchical terms** — adjacency list + materialized paths for O(1) ancestor/descendant queries without recursive CTEs. `Engineering > Backend > Database > Indexing` is a real, queryable path.

🤖 **TaxonomyCategorizer** — a dedicated service in the AI pipeline that analyzes content against your actual vocabulary structure and suggests or applies terms automatically, with confidence scores and provenance tracking per assignment

🔌 **16 REST endpoints** — CRUD for vocabularies, terms (including tree operations), content-term assignment, taxonomy-filtered content queries. Full OpenAPI spec.

🖱️ **6 Vue 3 components** — including a drag-and-drop TermTree and a multi-select TermPicker, production-ready out of the box

The database layer is worth calling out: three new tables, materialized path indexing (MySQL 768-char prefix to stay within 3072-byte key limit; SQLite auto-detected at migration time), circular reference guards, and cross-vocabulary validation. It's not an afterthought.

**Why this matters for content teams:** taxonomy maintenance is a silent tax on editorial velocity. At scale, manual categorization breaks down. You end up with 400 near-duplicate tags and an API that can't filter meaningfully. AI categorization that runs as part of your standard content pipeline eliminates that overhead entirely.

WordPress has categories and plugins. Contentful has flat global tags. Strapi and Directus make you build your own taxonomy infrastructure per project. Numen ships it — and adds the AI layer none of them have.

Open source, MIT license. Built on Laravel 12 + Vue 3.

→ https://github.com/byte5digital/numen

Curious what content organization challenges you're dealing with at scale. What would you want the AI categorizer to do that we haven't thought of yet?
```

---

## 3. Reddit Post (r/laravel + r/webdev)

**Title:**
`We built AI-native taxonomy into our open-source CMS (Laravel 12) — content gets categorized automatically during the AI pipeline`

**Body:**
```
Hey r/laravel — we just shipped v0.3.0 of Numen, our AI-first headless CMS, and the headlining feature is something I haven't seen anywhere else: taxonomy that categorizes your content for you.

**What we built:**

- **Vocabularies** — independent taxonomy namespaces per space. Not just "categories" and "tags" — you define your own organizational dimensions (Topics, Audience, Content Type, whatever). Each has its own term tree, config, and API surface.

- **Hierarchical terms** — adjacency list + materialized path column. Unlimited depth, fast ancestor queries without recursive CTEs. Drag-and-drop reordering in the admin UI.

- **TaxonomyCategorizer** — this is the interesting part. It's a service in the AI content pipeline that analyzes each piece against YOUR vocabulary structure and assigns terms automatically. Confidence scores per assignment, provenance tracked (human vs. model). Works with Anthropic, OpenAI, or Azure via our existing provider fallback chain.

- **16 REST endpoints** — full headless control. Vocabulary CRUD, term CRUD (tree ops included), content-term assignment, taxonomy-filtered content queries.

**Implementation details if you're curious:**

Three new tables: `vocabularies`, `taxonomy_terms` (with a materialized `path` column), and `content_taxonomy` (pivot with `confidence` and `source` fields for AI metadata).

MySQL path index uses a 768-char prefix to stay within MySQL's 3072-byte key limit. SQLite driver is auto-detected at migration time and falls back to plain indexes (prefix indexes not supported). Circular reference guards on every term write.

Test suite sits at 332 tests / 752 assertions, all passing.

**The honest take:**

This started as "we need taxonomy for the CMS" and turned into "wait, why doesn't the AI that's already touching every piece of content just organize it too?" The categorizer doesn't do keyword matching — it sends the content to the LLM with your vocabulary structure as context and asks it to suggest placements. The results are surprisingly good.

WordPress has `register_taxonomy()` (powerful but manual). Strapi makes you build category structures as collection types per project. Contentful's tags are flat and global. None of them have an AI layer.

Repo: https://github.com/byte5digital/numen
Docs for taxonomy specifically: `docs/features/taxonomy.md`

Happy to answer implementation questions. The materialized path stuff in particular has some interesting tradeoffs worth discussing if anyone's done similar work.
```

---

## 4. Hacker News Submission

**Title:**
`Numen – open-source CMS where the AI pipeline auto-categorizes content with confidence scores (Laravel/Vue)`

**Description (for text field / comment):**
```
We shipped taxonomy in Numen v0.3.0 and the interesting part isn't the taxonomy itself — it's that categorization runs as a pipeline stage alongside content generation and review.

The TaxonomyCategorizer service takes your actual vocabulary structure (the terms you've defined), sends content + structure to the LLM, and gets back term assignments with confidence scores. Provenance is tracked per assignment so you know whether a human or the model applied each term. Works with Anthropic, OpenAI, Azure via existing provider chains.

Under the hood: adjacency list + materialized path column for O(1) ancestor queries, 768-char MySQL prefix index to stay within InnoDB's 3072-byte key limit, SQLite auto-detection at migration time. 16 REST endpoints, 6 Vue 3 components, 332 tests / 752 assertions.

The comparison that keeps coming up: WordPress has register_taxonomy() (you write the integration), Contentful has flat global tags, Strapi/Directus give you primitives to build taxonomy yourself. None have an AI categorization layer.

MIT. Laravel 12 + Vue 3.

https://github.com/byte5digital/numen
```

---

_End of taxonomy-launch-social.md_
