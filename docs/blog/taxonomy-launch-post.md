# Your CMS Doesn't Understand Your Content. Numen Does.

_Announcing AI-Powered Taxonomy in Numen v0.3.0_

---

Every CMS eventually ships categories and tags. And in every CMS, they're an afterthought — flat lists bolted onto content models, managed entirely by humans, and ignored the moment your content library crosses a few hundred pieces.

We just shipped something different.

Numen v0.3.0 introduces a complete Taxonomy & Content Organization system — hierarchical vocabularies, multi-dimensional content assignment, a full REST API, and the part that changes the game: **AI auto-categorization baked directly into the content pipeline.**

No plugins. No third-party integrations. Your content gets analyzed, understood, and categorized as part of the same AI pipeline that generates and reviews it.

## The Problem Nobody Talks About

Here's the dirty secret of content at scale: taxonomy maintenance is a silent tax on editorial velocity.

You start with a clean set of categories. Six months later, you have 400 near-duplicate tags, three different spellings of "machine learning," and an editorial team that stopped categorizing content because the picker is unusable. Your headless frontend can't filter meaningfully because the organizational layer is chaos.

WordPress gives you `register_taxonomy()` and leaves you to code the rest. Strapi makes you reinvent category structures as collection types for every project. Contentful's tags are flat, global, and unstructured — a search convenience, not a taxonomy system. Directus hands you custom junction tables and wishes you luck.

Every one of these platforms treats categorization as a human problem. We think that's wrong.

## What We Built

### Vocabularies — Real Organizational Systems

Create unlimited, independent taxonomy namespaces per space. Not just "Categories" and "Tags" — but "Topics," "Audience," "Content Type," "Region," whatever your content strategy demands. Each vocabulary has its own term tree, its own configuration (hierarchical? allow multiple selections?), and its own API surface.

```bash
# Create a vocabulary
curl -X POST /api/v1/spaces/1/vocabularies \
  -d '{"name": "Topics", "slug": "topics", "is_hierarchical": true, "allow_multiple": true}'

# Add terms with hierarchy
curl -X POST /api/v1/vocabularies/1/terms \
  -d '{"name": "Backend", "parent_id": null}'

curl -X POST /api/v1/vocabularies/1/terms \
  -d '{"name": "Database", "parent_id": 1}'
```

### Hierarchical Terms — Unlimited Depth, Zero Compromise

`Engineering > Backend > Database > Indexing` is a real term path. We use an adjacency list with materialized paths for fast ancestor/descendant queries — no expensive recursive CTEs, no depth limits. Drag-and-drop reordering in the admin UI, with changes persisted immediately.

Each term carries SEO-friendly slugs, descriptions, and custom metadata fields (icon, color, image URL). This isn't a tag cloud. It's a proper information architecture tool.

### Multi-Dimensional Content Assignment

One content item, multiple vocabulary dimensions, all queryable independently:

```bash
# Assign terms from different vocabularies to content
curl -X POST /api/v1/content/42/terms \
  -d '{"term_ids": [5, 12, 27]}'

# Result: Content #42 is now tagged as:
# [Topics: Laravel, PHP] [Audience: Intermediate] [Type: Tutorial]
```

A many-to-many pivot with sort order means your frontend can query by any dimension or combination of dimensions. This is what headless-first taxonomy looks like.

## The AI Angle — This Is the Centerpiece

Here's where Numen stops being "another CMS with categories" and starts being something new.

When content moves through Numen's AI pipeline — the same pipeline that generates, illustrates, and reviews content — the `TaxonomyCategorizer` service analyzes each piece against your actual vocabulary structure. It doesn't match keywords. It doesn't scrape tag clouds. It *understands* where content belongs in your organizational hierarchy.

The result:

- **Automatic term suggestions** with confidence scores per assignment
- **Provenance tracking** — you always know whether a term was applied by a human editor or by the AI model
- **Provider-agnostic** — works with Anthropic, OpenAI, or Azure, using the same fallback chains as the rest of the pipeline
- **Respects your structure** — the AI suggests terms from *your* vocabularies, not some generic taxonomy. It learns your organizational language.

This is the difference between a CMS that stores content and a CMS that understands it.

No other open-source CMS — and frankly, no commercial one we've evaluated — ships AI-native taxonomy. WordPress has plugins. Contentful has flat tags. Strapi has collection types you wire together yourself. None of them analyze your content and categorize it automatically as a built-in pipeline stage.

## Under the Hood

For the technically curious:

- **Database:** Three new tables — `vocabularies`, `taxonomy_terms` (with materialized `path` column), and `content_taxonomy` (pivot with AI metadata fields for confidence and provenance)
- **Performance:** MySQL path indexes use 768-char prefix to stay within the 3072-byte key limit; SQLite driver auto-detected at migration time with plain indexes
- **Security:** Circular reference guards prevent infinite loops in term hierarchies. Cross-vocabulary validation ensures terms can't leak between spaces. Bounded JSON arrays prevent payload abuse.
- **API surface:** 16 REST endpoints covering vocabulary CRUD, term CRUD (including tree operations), content-term assignment/removal, term lookup by slug, and taxonomy-filtered content queries. Full OpenAPI spec updated.
- **Admin UI:** 6 Vue 3 components — `TaxonomyManager`, `TermTree`, `TermPicker`, `TermBreadcrumb`, `VocabularyForm`, and `TermForm`. Drag-and-drop powered, reactive, production-ready.
- **Test coverage:** 332 tests, 752 assertions, all green.

## How It Compares

| Capability | WordPress | Strapi | Contentful | **Numen** |
|---|---|---|---|---|
| Multiple taxonomy types | Custom code | DIY collection types | ❌ Flat tags only | ✅ UI-configured vocabularies |
| Hierarchical terms | Categories only | Manual modeling | ❌ | ✅ Unlimited depth, all vocabularies |
| Multi-vocabulary assignment | Possible but unwieldy | Manual junction tables | ❌ | ✅ First-class, API-native |
| AI auto-categorization | ❌ | ❌ | ❌ | ✅ Built into the pipeline |
| Confidence scores | ❌ | ❌ | ❌ | ✅ Per-assignment |
| Headless API | Limited filtering | ✅ | ✅ | ✅ 16 dedicated endpoints |

## What's Next

This is v0.3.0, not the finish line. Coming soon:

- **Taxonomy-based content filtering** in the public API (`?terms[]=slug`) for frontend developers
- **Bulk retroactive categorization** — run the AI categorizer across your entire existing content library in one job
- **Taxonomy analytics** — most-used terms, coverage gaps, confidence distribution dashboards

## Try It

Numen is open source under the MIT license.

```bash
git clone https://github.com/byte5digital/numen.git
cd numen
composer install && npm install
php artisan migrate
```

Create a vocabulary, add some terms, run content through the pipeline, and watch the AI categorize it for you. Then try doing that in any other CMS.

---

*Numen is an AI-first open-source CMS built with Laravel 12, Vue 3, and the conviction that content management should be intelligent by default. Built by [byte5.labs](https://byte5.digital).*
