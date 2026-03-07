# Taxonomy & Content Organization — Launch Marketing Copy

_Prepared by Herald 📣 | Numen AI-First CMS | 2026-03-07_

---

## 1. GitHub Discussion Announcement Post

**Title:** 🗂️ Shipped: AI-Powered Taxonomy System (v0.2.0)

---

We just merged the taxonomy system into `dev` and cut v0.2.0. Here's what landed and why it matters.

### The problem we were solving

Content without structure is just a pile. Every CMS eventually ships categories and tags — but almost all of them treat it as an afterthought: flat lists, single hierarchies, and zero intelligence. You end up with 400 near-duplicate tags, editorial debt you never repay, and an API that can't filter anything meaningfully.

We wanted something better. We also wanted to use AI where it actually helps instead of where it looks impressive.

### What shipped

**Vocabularies**

Multiple independent taxonomy namespaces per space. Create "Topics," "Audience," "Content Type," and "Region" as separate vocabularies — each with its own term tree, its own configuration (allow multiple? hierarchical?), and its own API surface. No more one-size-fits-all tag clouds.

**Hierarchical Terms**

Unlimited nesting. `Engineering > Backend > Database > Indexing` is a real term path. We use an adjacency list with materialized paths for fast ancestor/descendant queries without expensive recursive CTEs. Drag-and-drop reordering in the admin, with changes persisted immediately.

**Multi-Vocabulary Content Assignment**

One content item can hold terms from every vocabulary simultaneously. A tutorial can be `[Topics: Laravel, PHP] [Audience: Intermediate] [Type: Tutorial]` — each dimension queryable independently or in combination via the headless API.

**AI Auto-Categorization** ← this is the new part

When content moves through the AI pipeline, `TaxonomyCategorizer` analyzes the content against your actual vocabulary structure and suggests — or directly applies — terms. Confidence scores are stored per assignment so you know what the model was certain about and what it hedged on. Provenance is tracked: you can always tell whether a term was applied by a human or by the model.

This isn't keyword matching or tag cloud scraping. The same LLM that generates and reviews your content understands where it belongs in your organizational hierarchy. Works with all configured providers: Anthropic, OpenAI, Azure.

**REST API — 16 endpoints**

Full programmatic control: CRUD for vocabularies, CRUD for terms (with tree operations), content-term assignment/removal, term lookup by slug, content filtering by taxonomy. All endpoints are documented in the updated OpenAPI spec.

**6 Vue Components**

- `TaxonomyManager` — vocabulary CRUD
- `TermTree` — drag-and-drop tree view
- `TermPicker` — multi-select picker for content assignment
- `TermBreadcrumb` — hierarchy trail display
- `VocabularyForm` / `TermForm` — creation/edit forms

---

### How to use it

1. Go to admin → Taxonomies → Create a vocabulary
2. Add your term hierarchy
3. Assign terms to content manually, or let the AI pipeline do it
4. Query `/api/v1/taxonomies` and `/api/v1/content/{id}/terms` from your frontend

Full docs: `docs/features/taxonomy.md`

---

### What's next

- Taxonomy-based content filtering in the public content API (`?terms[]=slug`)
- Bulk retroactive categorization job for existing content libraries
- Taxonomy analytics: most-used terms, coverage gaps, confidence distribution

---

If you hit edge cases, post them here. If you want to contribute the batch categorization feature, that's a great first PR.

---

## 2. Key Messaging Points (Social / Landing Page)

**For use as bullet points, feature cards, or social copy — pick and mix.**

- 🤖 **AI that categorizes for you** — Content gets analyzed and tagged automatically during the AI pipeline. Confidence scores included. No more manual overhead at scale.
- 🗂️ **Multiple vocabularies, real hierarchy** — Topics, Audiences, Content Types — separate organizational systems, unlimited nesting, no flat tag cloud compromise.
- 📌 **Multi-dimensional assignment** — One piece of content, multiple vocabulary dimensions, all queryable and filterable via the headless API from day one.
- 🖱️ **Drag-and-drop tree management** — Reorganize your entire term hierarchy visually. Move branches, reorder siblings, restructure — no SQL required.
- 🔌 **16 REST endpoints, zero integration debt** — Vocabularies, terms, and content-term relationships are fully exposed. Your frontend queries content by taxonomy on day one.

---

## 3. Changelog Entry

### [0.2.0] — 2026-03-07

#### Added — Taxonomy & Content Organization

- **Vocabulary system** — Create unlimited taxonomy vocabularies per space (Categories, Tags, Topics, Audience, Content Type, etc.). Configurable hierarchy depth and multi-select cardinality (`allow_multiple`).
- **Hierarchical terms** — Nested term trees using adjacency list + materialized path for fast ancestor queries. SEO-friendly slugs, descriptions, and custom metadata fields (icon, color, image URL).
- **Multi-vocabulary content assignment** — Many-to-many pivot (`content_taxonomy`) supporting terms from multiple vocabularies on a single content item, with sort order.
- **AI auto-categorization** — `TaxonomyCategorizer` service integrates with the content pipeline to automatically suggest and apply taxonomy terms. Confidence scores and provenance tracked per assignment.
- **Taxonomy admin UI** — Full CRUD for vocabularies and terms. Drag-and-drop tree reordering. Breadcrumb trail display.
- **REST API (16 endpoints)** — CRUD for vocabularies, terms (including tree operations), content-term assignments, and taxonomy-filtered content queries. OpenAPI spec updated.
- **6 Vue components** — `TaxonomyManager`, `TermTree`, `TermPicker`, `TermBreadcrumb`, `VocabularyForm`, `TermForm`.

#### Technical

- New DB tables: `vocabularies`, `taxonomy_terms` (with materialized `path` column), `content_taxonomy` (pivot with AI metadata)
- New models: `Vocabulary`, `TaxonomyTerm`
- New services: `TaxonomyService`, `TaxonomyCategorizer`
- New controllers: `TaxonomyAdminController`, `TaxonomyController`, `TaxonomyTermController`
- MySQL: path index uses 768-char prefix to stay within 3072-byte key limit
- SQLite: plain index (prefix indexes not supported); driver detected at migration time
- Test suite: 332 tests, 752 assertions — all passing

---

## 4. Competitive Comparison

### vs. WordPress (Categories + Tags)

| Capability | WordPress | Numen |
|---|---|---|
| Multiple taxonomy types | ✅ Custom taxonomies via `register_taxonomy()` | ✅ Vocabularies, no code required |
| Hierarchical terms | ✅ (Categories only) | ✅ All vocabularies, unlimited depth |
| Multi-vocabulary assignment | ⚠️ Possible but unwieldy | ✅ First-class, API-native |
| AI auto-categorization | ❌ Plugin territory; none native | ✅ Built into the AI pipeline |
| Confidence scores / provenance | ❌ | ✅ Per-assignment |
| Headless API | ⚠️ REST API exists; taxonomy filtering limited | ✅ Full taxonomy API, 16 endpoints |
| Admin UI quality | ✅ Mature | ✅ Modern Vue 3, drag-and-drop |

**Verdict:** WordPress taxonomy is powerful but manual, code-configured, and has no AI layer. Numen's taxonomy is UI-configured, headless-first, and AI-categorizes your content by default.

---

### vs. Strapi (Categories via Collection Types + Relations)

Strapi doesn't have a native taxonomy system. You build category/tag structures as collection types with relation fields — flexible, but you're reinventing the wheel every project. There's no concept of vocabulary hierarchy, no materialized paths, no multi-vocabulary assignment as a first-class feature. And zero AI categorization.

**Verdict:** Strapi gives you the primitives to build taxonomy. Numen ships taxonomy. For teams that don't want to rebuild organizational infrastructure per project, Numen wins.

---

### vs. Contentful (Tags)

Contentful's tag system is flat, global, and unstructured. Tags exist outside the content model — they're a bolt-on organizational layer, not a first-class taxonomy. No hierarchies. No vocabularies. No per-space scoping. No AI.

**Verdict:** Contentful tags are a search/filter convenience, not a taxonomy system. If content organization at scale matters to your team, Contentful's approach will eventually become a liability. Numen's vocabulary-based approach scales with your content strategy.

---

### vs. Directus (Folders + Tags)

Directus offers file folders and a basic tags field. For content taxonomy, you're back to custom junction tables and collection configuration. Hierarchical categories require custom data modeling. No AI layer.

**Verdict:** Similar to Strapi — Directus gives you tools, not a taxonomy system. The engineering overhead of building a proper taxonomy on top of Directus is non-trivial. Numen ships it ready.

---

### Summary Position

> **Numen is the only open-source CMS with AI-native taxonomy.** Every other platform — open source or commercial — treats categorization as a human problem. We treat it as a pipeline stage.

For content teams managing hundreds or thousands of pieces, this isn't a nice-to-have. Manual taxonomy maintenance is a silent tax on editorial velocity. Numen eliminates it.

---

## 5. README Feature Block

_For insertion in the main README under "Features" section_

---

### 🗂️ AI-Powered Taxonomy

**Content organization that works at scale — without the manual overhead.**

- **Multiple vocabularies** — create Topics, Audiences, Content Types, and more as separate taxonomy systems per space
- **Hierarchical terms** — unlimited nesting with drag-and-drop tree management in the admin UI
- **Multi-vocabulary assignment** — tag content across every dimension simultaneously; filter via the headless API
- **AI auto-categorization** — content is analyzed and tagged automatically during the pipeline, with confidence scores and provenance tracking per assignment
- **16 REST API endpoints** — full programmatic control from any frontend, framework, or integration

No other open-source CMS does AI-native taxonomy. This is the difference between a CMS that stores content and a CMS that understands it.

---

_End of taxonomy-launch.md_
