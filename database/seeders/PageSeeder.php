<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageComponent;
use App\Models\Space;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $space = Space::where('slug', 'byte5-labs')->firstOrFail();

        $page = Page::firstOrCreate(
            ['space_id' => $space->id, 'slug' => 'home'],
            [
                'title'        => 'Home',
                'status'       => 'published',
                'published_at' => now(),
                'meta'         => [
                    'title'       => 'Numen — Content creation as a background process',
                    'description' => 'Numen inverts the content creation paradigm. You write briefs, not articles. Three AI agents generate, optimize, and review — humans curate.',
                ],
            ]
        );

        if ($page->components()->count() > 0) {
            $this->command->info('⏭  Home page already seeded, skipping.');
            return;
        }

        $components = [
            [
                'type'       => 'hero',
                'sort_order' => 1,
                'data'       => [
                    'badge'               => 'The CMS paradigm shift is here',
                    'headline'            => "Content creation\nas a background process",
                    'subline'             => 'Traditional CMS is dead. Numen inverts the paradigm: you write briefs, not articles. Three AI agents generate, optimize, and review — humans curate.',
                    'cta_primary_label'   => 'Read the Blog →',
                    'cta_primary_href'    => '/blog',
                    'cta_secondary_label' => 'Browse API',
                    'cta_secondary_href'  => '/api/v1/content',
                ],
            ],
            [
                'type'       => 'stats_row',
                'sort_order' => 2,
                'data'       => [
                    'stats' => [
                        ['label' => 'Published Articles', 'value_source' => 'published_count', 'color' => '#ffffff'],
                        ['label' => 'AI Generations',    'value_source' => 'total_generated',  'color' => '#ffffff'],
                        ['label' => 'AI Personas',       'value'        => '3',                'color' => '#ffffff'],
                        ['label' => 'Avg Cost / Article','value_source' => 'avg_cost',          'color' => '#22c55e', 'prefix' => '$'],
                    ],
                ],
            ],
            [
                'type'       => 'pipeline_steps',
                'sort_order' => 3,
                'data'       => [
                    'headline' => 'The Pipeline',
                    'subline'  => 'One sentence in. Published, SEO-optimized content out. Under $0.25.',
                    'steps'    => [
                        ['name' => 'Brief',    'description' => 'One sentence input', 'color' => '#1E9BD7'],
                        ['name' => 'Generate', 'description' => 'Sonnet 4 writes',    'color' => '#1E9BD7'],
                        ['name' => 'SEO',      'description' => 'Haiku optimizes',    'color' => '#EA5172'],
                        ['name' => 'Review',   'description' => 'Opus judges',        'color' => '#1E9BD7'],
                        ['name' => 'Publish',  'description' => 'Auto or manual',     'color' => '#22c55e'],
                    ],
                ],
            ],
            [
                'type'       => 'feature_grid',
                'sort_order' => 4,
                'data'       => [
                    'headline' => 'Why AI-First?',
                    'features' => [
                        [
                            'icon'        => '⚡',
                            'title'       => 'AI-First Pipeline',
                            'description' => 'Submit a brief — AI generates, optimizes, reviews, and publishes. Content creation as a background process.',
                        ],
                        [
                            'icon'        => '🤖',
                            'title'       => 'Multi-Agent Architecture',
                            'description' => 'Three specialized AI personas: Content Creator (Sonnet 4), SEO Expert (Haiku 4.5), Editorial Director (Opus 4).',
                        ],
                        [
                            'icon'        => '🔌',
                            'title'       => 'Headless API',
                            'description' => 'Clean REST API serves structured content to any frontend. React, Vue, Next.js, mobile — you choose.',
                        ],
                        [
                            'icon'        => '📊',
                            'title'       => 'Full Observability',
                            'description' => 'Every AI call logged with tokens, cost, latency. Complete provenance chain from brief to published content.',
                        ],
                        [
                            'icon'        => '🛡️',
                            'title'       => 'Human-in-the-Loop',
                            'description' => 'Quality gates with configurable thresholds. AI publishes autonomously above 80 — humans review the rest.',
                        ],
                        [
                            'icon'        => '💰',
                            'title'       => '$0.25 per Article',
                            'description' => '~$25/month for 100 articles through the full pipeline. Compare that to a content team.',
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'content_list',
                'sort_order' => 5,
                'data'       => [
                    'headline'     => 'Latest from the Pipeline',
                    'subline'      => 'Written, optimized, and reviewed by AI',
                    'limit'        => 5,
                    'view_all_href' => '/blog',
                ],
            ],
            [
                'type'       => 'tech_stack',
                'sort_order' => 6,
                'data'       => [
                    'headline' => 'Built with',
                    'subline'  => 'Production-grade stack by byte5.labs',
                    'items'    => [
                        ['icon' => '🐘', 'label' => 'Laravel 12'],
                        ['icon' => '🟢', 'label' => 'Vue 3 + Inertia'],
                        ['icon' => '🎨', 'label' => 'Tailwind 4'],
                        ['icon' => '🧠', 'label' => 'Claude (Anthropic)'],
                        ['icon' => '⚡', 'label' => 'bytyBot'],
                    ],
                ],
            ],
            [
                'type'       => 'cta_block',
                'sort_order' => 7,
                'data'       => [
                    'headline'            => 'Ready to kill your CMS?',
                    'body'                => 'Let AI handle content creation. You handle strategy.',
                    'cta_primary_label'   => 'Get Started',
                    'cta_primary_href'    => '/login',
                    'cta_secondary_label' => 'Contact byte5',
                    'cta_secondary_href'  => 'https://www.byte5.de/kontakt',
                ],
            ],
        ];

        foreach ($components as $component) {
            PageComponent::create(array_merge($component, ['page_id' => $page->id]));
        }

        $this->command->info("✅ Home page seeded with " . count($components) . " components.");
    }
}
