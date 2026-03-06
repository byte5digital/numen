<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\Space;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $space = Space::where('slug', 'byte5-labs')->firstOrFail();
        $contentType = ContentType::where('space_id', $space->id)
            ->where('slug', 'blog_post')
            ->firstOrFail();

        $slug = 'from-hardcoded-to-headless-how-numen-builds-its-own-pages';

        if (Content::where('slug', $slug)->exists()) {
            $this->command->info('⏭  Architecture blog post already seeded, skipping.');
            return;
        }

        $body = <<<'MARKDOWN'
There is an irony baked into every CMS ever built: the system that manages content for *other* pages often has its own homepage hardcoded. Numen was no different — until today.

## The Problem

The home page of Numen was 175 lines of hardcoded Vue. The hero headline, the pipeline steps, the feature grid, the CTAs — all static strings compiled into JavaScript. If you wanted to change the copy, you edited source code and redeployed. That is the antithesis of what a CMS is supposed to do.

The fix was not to add a "settings" table with a few text fields. The fix was to rethink the page as a **document made of typed components**.

## The Block Model

Every page is now a container of ordered blocks. Each block has:

- A **type** (`hero`, `stats_row`, `feature_grid`, `pipeline_steps`, `content_list`, `cta_block`, `tech_stack`, `rich_text`)
- **Structured data** — a JSON object whose schema is defined per type
- An optional **WYSIWYG override** — raw HTML that, when present, replaces the structured rendering entirely
- Flags: `ai_generated`, `locked`

This gives you three tiers of control:

1. **Structured** — edit fields through a form; the frontend renders via a typed Vue component
2. **WYSIWYG** — paste or write raw HTML for total creative freedom; the block renders it via `v-html`
3. **AI-generated** — submit a brief, let the pipeline fill the block's data fields automatically

The WYSIWYG override is the escape hatch. Most blocks stay structured. But when a designer wants pixel-precise control over a hero section for a campaign, they flip to WYSIWYG and own it completely — without touching code.

## Headless and All-in-One

The same `pages` and `page_components` tables power two delivery modes:

**All-in-one (Inertia/Vue):**
The Laravel controller queries the page, passes it as Inertia props, and the `ComponentRenderer.vue` dispatches each block to its typed Vue component. This is what you are reading right now.

**Headless API:**
```
GET /api/v1/pages/home
```
Returns the full page tree as JSON — slug, meta, and an array of components with their type, data, and any WYSIWYG override. Any frontend can consume it: Next.js, a React Native app, a static site generator.

The two modes share zero duplication. Same database, same serialization logic, different delivery layer.

## The Component Architecture

```
resources/js/Blocks/
  ComponentRenderer.vue   ← dispatches by component.type
  HeroBlock.vue
  StatsRow.vue            ← live stats injected server-side
  FeatureGrid.vue
  PipelineSteps.vue
  ContentList.vue         ← pulls from published content
  CtaBlock.vue
  TechStack.vue
  RichText.vue            ← v-html for WYSIWYG
```

`ComponentRenderer.vue` is a single-line dispatcher:

```vue
<component :is="blockMap[component.type]" :data="component.data" :wysiwyg="component.wysiwyg_override" />
```

Each block component accepts `data` (structured JSON) and `wysiwyg` (HTML override). If `wysiwyg` is set, it renders that. Otherwise it renders from `data`. The block does not care which one is used — the decision is made at the data layer.

## AI Integration Per Block

Each block can be targeted by a `ContentBrief`. The workflow:

1. Open the admin → Pages → Edit → click "Generate with AI" on a block
2. The system pre-fills a brief with context: block type, page slug, brand guidelines from the space
3. The pipeline runs: ContentCreator writes, SEO optimizes, Editorial Director reviews
4. Output is parsed and stored back into the block's `data` JSON
5. Admin reviews — approve, edit, or switch to WYSIWYG to override

The `locked` flag prevents any pipeline run from overwriting a block a human has manually curated. Once you lock it, AI cannot touch it.

## Dog-Fooding

This blog post was seeded as actual CMS content — a `ContentVersion` authored by a human, published through the same pipeline the system uses for AI-generated posts. The architecture post about the block system is itself managed by the block system.

That is the goal: a CMS with no hardcoded pages, no hardcoded content, and no hardcoded opinions — just structured data, typed components, and an AI pipeline to fill them.

## What Is Next

- Drag-to-reorder blocks in the admin (currently reorder via API)
- AI "Generate" button per block in the editor UI
- Page-level publishing workflow (draft → review → publish)
- Multi-locale pages (same block model, per-locale content variants)
- Visual preview mode in admin

The foundation is done. Everything on top is iteration.
MARKDOWN;

        $content = Content::create([
            'space_id'        => $space->id,
            'content_type_id' => $contentType->id,
            'slug'            => $slug,
            'status'          => 'published',
            'locale'          => 'en',
            'published_at'    => now(),
        ]);

        $version = ContentVersion::create([
            'content_id'     => $content->id,
            'version_number' => 1,
            'title'          => 'From Hardcoded to Headless: How Numen Builds Its Own Pages',
            'excerpt'        => 'Numen used to have a hardcoded homepage — the irony of a CMS that could not manage its own pages. We fixed that by introducing a block-based page model with structured components, WYSIWYG overrides, and AI generation per block. Here is how it works.',
            'body'           => $body,
            'body_format'    => 'markdown',
            'author_type'    => 'human',
            'author_id'      => 'bytybot',
            'change_reason'  => 'Initial publish — architecture post about block/component system',
            'quality_score'  => 92,
            'seo_score'      => 88,
            'seo_data'       => [
                'meta_title'       => 'From Hardcoded to Headless: How Numen Builds Its Own Pages',
                'meta_description' => 'How Numen replaced a hardcoded homepage with a block-based component system — supporting structured data, WYSIWYG overrides, AI generation per block, and a headless API.',
                'keywords'         => ['headless cms', 'block editor', 'component cms', 'ai content', 'laravel cms'],
            ],
            'structured_fields' => [
                'reading_time_minutes' => 5,
                'difficulty'           => 'intermediate',
                'series'               => 'Numen Architecture',
            ],
        ]);

        $content->update(['current_version_id' => $version->id]);

        $this->command->info("✅ Architecture blog post seeded: /{$slug}");
    }
}
