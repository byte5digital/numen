<?php

namespace Database\Seeders;

use App\Models\Content;
use Illuminate\Database\Seeder;

/**
 * Converts the architecture blog post's markdown body into structured content blocks.
 *
 * Run this seeder once after running BlogPostSeeder to migrate the existing
 * blog post from raw markdown body to the block-based rendering system.
 */
class ContentBlockSeeder extends Seeder
{
    public function run(): void
    {
        $slug = 'from-hardcoded-to-headless-how-numen-builds-its-own-pages';
        $content = Content::where('slug', $slug)->first();

        if (! $content) {
            $this->command->warn('⏭  Blog post not found, run BlogPostSeeder first.');

            return;
        }

        $version = $content->currentVersion;
        if (! $version) {
            $this->command->warn('⏭  No current version found for blog post.');

            return;
        }

        if ($version->blocks()->exists()) {
            $this->command->info('⏭  Content blocks already seeded for this post, skipping.');

            return;
        }

        $blocks = [
            [
                'type' => 'paragraph',
                'sort_order' => 0,
                'data' => [
                    'text' => 'There is an irony baked into every CMS ever built: the system that manages content for *other* pages often has its own homepage hardcoded. Numen was no different — until today.',
                ],
            ],
            [
                'type' => 'heading',
                'sort_order' => 1,
                'data' => ['level' => 'h2', 'text' => 'The Problem'],
            ],
            [
                'type' => 'paragraph',
                'sort_order' => 2,
                'data' => [
                    'text' => "The home page of Numen was 175 lines of hardcoded Vue. The hero headline, the pipeline steps, the feature grid, the CTAs — all static strings compiled into JavaScript. If you wanted to change the copy, you edited source code and redeployed. That is the antithesis of what a CMS is supposed to do.\n\nThe fix was not to add a \"settings\" table with a few text fields. The fix was to rethink the page as a **document made of typed components**.",
                ],
            ],
            [
                'type' => 'heading',
                'sort_order' => 3,
                'data' => ['level' => 'h2', 'text' => 'The Block Model'],
            ],
            [
                'type' => 'paragraph',
                'sort_order' => 4,
                'data' => [
                    'text' => "Every page is now a container of ordered blocks. Each block has a **type** (`hero`, `stats_row`, `feature_grid`…), **structured data** — a JSON object whose schema is defined per type — an optional **WYSIWYG override**, and flags: `ai_generated`, `locked`.\n\nThis gives you three tiers of control:\n\n1. **Structured** — edit fields through a form; the frontend renders via a typed Vue component\n2. **WYSIWYG** — paste raw HTML for total creative freedom\n3. **AI-generated** — submit a brief, let the pipeline fill the block's data fields automatically\n\nThe WYSIWYG override is the escape hatch. Most blocks stay structured. But when a designer wants pixel-precise control over a hero section, they flip to WYSIWYG and own it completely — without touching code.",
                ],
            ],
            [
                'type' => 'heading',
                'sort_order' => 5,
                'data' => ['level' => 'h2', 'text' => 'Headless and All-in-One'],
            ],
            [
                'type' => 'paragraph',
                'sort_order' => 6,
                'data' => [
                    'text' => "The same `pages` and `page_components` tables power two delivery modes:\n\n**All-in-one (Inertia/Vue):** The Laravel controller queries the page, passes it as Inertia props, and `ComponentRenderer.vue` dispatches each block to its typed Vue component.\n\n**Headless API:** `GET /api/v1/pages/home` returns the full page tree as JSON. Any frontend can consume it: Next.js, a React Native app, a static site generator.\n\nThe two modes share zero duplication. Same database, same serialization logic, different delivery layer.",
                ],
            ],
            [
                'type' => 'heading',
                'sort_order' => 7,
                'data' => ['level' => 'h2', 'text' => 'The Component Architecture'],
            ],
            [
                'type' => 'code_block',
                'sort_order' => 8,
                'data' => [
                    'language' => 'text',
                    'code' => "resources/js/Blocks/\n  ComponentRenderer.vue   ← dispatches by component.type\n  HeroBlock.vue\n  StatsRow.vue            ← live stats injected server-side\n  FeatureGrid.vue\n  PipelineSteps.vue\n  ContentList.vue         ← pulls from published content\n  CtaBlock.vue\n  TechStack.vue\n  RichText.vue            ← v-html for WYSIWYG",
                ],
            ],
            [
                'type' => 'paragraph',
                'sort_order' => 9,
                'data' => [
                    'text' => '`ComponentRenderer.vue` is a single-line dispatcher. Each block component accepts `data` (structured JSON) and `wysiwyg` (HTML override). If `wysiwyg` is set, it renders that. Otherwise it renders from `data`. The block does not care which one is used — the decision is made at the data layer.',
                ],
            ],
            [
                'type' => 'heading',
                'sort_order' => 10,
                'data' => ['level' => 'h2', 'text' => 'Multi-Provider AI Integration'],
            ],
            [
                'type' => 'callout',
                'sort_order' => 11,
                'data' => [
                    'variant' => 'info',
                    'title' => 'New in this release',
                    'body' => 'Numen now supports Anthropic, OpenAI, and Azure AI Foundry as LLM backends. A fallback chain automatically routes to the next available provider when rate limits or outages occur.',
                ],
            ],
            [
                'type' => 'paragraph',
                'sort_order' => 12,
                'data' => [
                    'text' => "The `LLMManager` resolves the provider from the model name (or an explicit `provider:model` prefix) and walks the fallback chain on 429 or 5xx responses. Each pipeline role can target a different model — e.g. `AI_MODEL_GENERATION=claude-sonnet-4-6` and `AI_MODEL_SEO=openai:gpt-4o-mini`.\n\nPer-block AI generation works the same way: open Admin → Pages → Edit, click **Generate with AI** on any block, and the pipeline fills that block's structured fields automatically. The `locked` flag prevents any future AI run from overwriting a block a human has curated.",
                ],
            ],
            [
                'type' => 'heading',
                'sort_order' => 13,
                'data' => ['level' => 'h2', 'text' => 'Dog-Fooding'],
            ],
            [
                'type' => 'paragraph',
                'sort_order' => 14,
                'data' => [
                    'text' => 'This blog post was seeded as actual CMS content — a `ContentVersion` authored by a human, published through the same pipeline the system uses for AI-generated posts. The architecture post about the block system is itself managed by the block system. That is the goal: a CMS with no hardcoded pages, no hardcoded content, and no hardcoded opinions — just structured data, typed components, and an AI pipeline to fill them.',
                ],
            ],
            [
                'type' => 'divider',
                'sort_order' => 15,
                'data' => [],
            ],
            [
                'type' => 'heading',
                'sort_order' => 16,
                'data' => ['level' => 'h3', 'text' => 'What Is Next'],
            ],
            [
                'type' => 'paragraph',
                'sort_order' => 17,
                'data' => [
                    'text' => "- Drag-to-reorder blocks in the admin\n- AI \"Generate\" button per block in the editor UI\n- Page-level publishing workflow (draft → review → publish)\n- Multi-locale pages (same block model, per-locale content variants)\n- Visual preview mode in admin\n\nThe foundation is done. Everything on top is iteration.",
                ],
            ],
        ];

        foreach ($blocks as $block) {
            $version->blocks()->create($block);
        }

        $this->command->info('✅ Content blocks seeded: '.count($blocks).' blocks for "'.$slug.'"');
    }
}
