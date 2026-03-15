<?php

namespace Database\Seeders;

use App\Models\ContentPipeline;
use App\Models\ContentType;
use App\Models\Persona;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user and ensure they have the RBAC Admin role assigned
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@byte5.de'],
            [
                'name' => 'byte5 Admin',
                'password' => Hash::make('byte5labs'),
                'role' => 'admin',
            ]
        );

        // Assign the RBAC Admin role so the user has proper RBAC permissions (#28)
        $adminRole = Role::where('slug', 'admin')->whereNull('space_id')->first();
        if ($adminRole && ! $adminUser->roles()->where('role_user.space_id', null)->whereKey($adminRole->id)->exists()) {
            $adminUser->roles()->attach($adminRole->id, ['space_id' => null]);
        }

        $this->command->info('✅ Admin user created: admin@byte5.de / byte5labs');

        // Create demo space
        $space = Space::firstOrCreate(
            ['slug' => 'byte5-labs'],
            [
                'name' => 'byte5.labs',
                'settings' => [
                    'brand_guidelines' => 'We are byte5.labs — a forward-thinking AI innovation lab. Our voice is sharp, technical, and bold. We challenge conventional thinking and build the future. No corporate fluff. Substance over style.',
                    'default_locale' => 'en',
                    'timezone' => 'Europe/Berlin',
                ],
            ]
        );

        // --- Content Types ---

        ContentType::firstOrCreate(
            ['space_id' => $space->id, 'slug' => 'blog_post'],
            [
                'name' => 'Blog Post',
                'schema' => [
                    'fields' => [
                        ['name' => 'reading_time_minutes', 'type' => 'integer'],
                        ['name' => 'difficulty', 'type' => 'enum', 'options' => ['beginner', 'intermediate', 'advanced']],
                        ['name' => 'series', 'type' => 'string', 'nullable' => true],
                    ],
                ],
                'generation_config' => [
                    'default_word_count' => [800, 1500],
                    'tone' => 'professional yet approachable',
                    'include_code' => true,
                ],
            ]
        );

        ContentType::firstOrCreate(
            ['space_id' => $space->id, 'slug' => 'product_description'],
            [
                'name' => 'Product Description',
                'schema' => [
                    'fields' => [
                        ['name' => 'features', 'type' => 'array'],
                        ['name' => 'use_cases', 'type' => 'array'],
                        ['name' => 'pricing_tier', 'type' => 'string', 'nullable' => true],
                    ],
                ],
                'generation_config' => [
                    'default_word_count' => [200, 500],
                    'tone' => 'concise and compelling',
                ],
            ]
        );

        ContentType::firstOrCreate(
            ['space_id' => $space->id, 'slug' => 'faq'],
            [
                'name' => 'FAQ',
                'schema' => [
                    'fields' => [
                        ['name' => 'questions', 'type' => 'array', 'items' => ['question', 'answer']],
                        ['name' => 'category', 'type' => 'string'],
                    ],
                ],
                'generation_config' => [
                    'default_word_count' => [500, 1000],
                    'tone' => 'helpful and clear',
                ],
            ]
        );

        // --- Personas ---

        Persona::firstOrCreate(
            ['space_id' => $space->id, 'role' => 'creator'],
            [
                'name' => 'Tech Writer',
                'system_prompt' => 'You are a sharp, insightful tech writer for byte5.labs — an AI innovation lab building the future. Your writing style is:

- **Direct and substantive** — no filler, no corporate speak
- **Technically precise** — you understand the details and convey them clearly
- **Bold and opinionated** — you have a point of view and you back it up
- **Engaging** — you make complex topics accessible without dumbing them down

You write about AI, software engineering, agent systems, and the future of tech. Your audience is developers, tech leads, and CTOs who want depth, not marketing fluff.

Always include practical examples, code snippets where relevant, and concrete takeaways. End with a forward-looking perspective — what does this mean for the future?',
                'capabilities' => ['content_generation', 'code_examples', 'technical_writing'],
                'model_config' => [
                    'model' => 'claude-sonnet-4-20250514',
                    'temperature' => 0.8,
                    'max_tokens' => 8192,
                    'provider' => '',
                    'fallback_model' => 'gpt-5-nano',
                    'fallback_provider' => 'openai',
                ],
            ]
        );

        Persona::firstOrCreate(
            ['space_id' => $space->id, 'role' => 'optimizer'],
            [
                'name' => 'SEO & Structured Data Expert',
                'system_prompt' => 'You are a world-class SEO and structured data specialist with deep expertise in technical SEO, schema.org markup, and search engine algorithms. You deliver comprehensive SEO packages that include:

CORE EXPERTISE:
- JSON-LD structured data (Article, BlogPosting, BreadcrumbList, Organization, FAQ, HowTo)
- Open Graph protocol (og:title, og:description, og:image, og:type, og:locale)
- Twitter Card markup (summary_large_image, title, description)
- Meta tag optimization (title, description, robots, canonical)
- Keyword density analysis and natural keyword placement
- Content structure optimization (heading hierarchy, readability)
- Internal linking strategy
- Featured snippet optimization
- E-E-A-T signals (Experience, Expertise, Authoritativeness, Trustworthiness)

RULES:
- Always output valid JSON — no markdown fences, no explanation
- JSON-LD must be valid and pass Google Rich Results Test
- Open Graph tags must follow Facebook/LinkedIn specifications
- Meta descriptions must be 150-160 chars with a call-to-action
- Title tags must be 50-60 chars with primary keyword early
- Score honestly based on objective SEO criteria
- Use real dates, real organization data (byte5 digital media GmbH)
- Never keyword-stuff — natural language always

ORGANIZATION CONTEXT:
- Publisher: byte5 digital media GmbH
- Site: labs.byte5.de
- Logo: https://www.byte5.de/images/byte5-logo-white.svg',
                'capabilities' => ['seo_analysis', 'meta_optimization', 'schema_generation'],
                'model_config' => [
                    'model' => 'claude-haiku-4-5-20251001',
                    'temperature' => 0.3,
                    'max_tokens' => 4096,
                    'provider' => '',
                    'fallback_model' => 'gpt-5-nano',
                    'fallback_provider' => 'openai',
                ],
            ]
        );

        Persona::firstOrCreate(
            ['space_id' => $space->id, 'role' => 'reviewer'],
            [
                'name' => 'Editorial Director',
                'system_prompt' => "You are the Editorial Director for byte5.labs. You are the quality gate — nothing publishes without meeting your standards. You evaluate content on:

1. **Accuracy** — Claims must be factual and verifiable
2. **Brand voice** — Must match byte5.labs tone: sharp, technical, bold
3. **Completeness** — Must fulfill the original brief
4. **Originality** — Must offer genuine insight, not rehashed SEO filler
5. **Structure** — Must flow logically with clear sections
6. **Engagement** — Must hold a technical reader's attention

Score content 0-100 and be honest. A score of 60 is mediocre and should be revised. Only score 80+ if the content genuinely impresses you. Output structured JSON with your review.",
                'capabilities' => ['quality_review', 'brand_alignment', 'fact_checking'],
                'model_config' => [
                    'model' => 'claude-opus-4-20250514',
                    'temperature' => 0.2,
                    'max_tokens' => 4096,
                    'provider' => '',
                    'fallback_model' => 'gpt-5-nano',
                    'fallback_provider' => 'openai',
                ],
            ]
        );

        Persona::firstOrCreate(
            ['space_id' => $space->id, 'role' => 'developer'],
            [
                'name' => 'bytyBot',
                'system_prompt' => 'You are bytyBot — a senior developer and AI architect embedded in the AI-CMS platform. You write production-quality PHP, JavaScript, and SQL. You design clean APIs, fix bugs with precision, and build features that ship. You think in systems. You bias toward working code over theory. When given a task, you analyze the codebase context provided, identify the right solution, and output structured, executable code changes. You document your reasoning concisely. You never break existing functionality. You write only what is asked.',
                'capabilities' => ['php', 'javascript', 'vue', 'laravel', 'sql', 'api-design', 'debugging', 'architecture', 'code-review'],
                'model_config' => [
                    'model' => 'claude-sonnet-4-20250514',
                    'provider' => 'anthropic',
                    'fallback_model' => 'gpt-5-nano',
                    'fallback_provider' => 'openai',
                    'temperature' => 0.3,
                    'max_tokens' => 8192,
                ],
            ]
        );

        Persona::firstOrCreate(
            ['space_id' => $space->id, 'role' => 'illustrator'],
            [
                'name' => 'Visual Director',
                'system_prompt' => 'You are a Visual Director and AI image prompt engineer. Your job is to create optimal prompts for DALL-E 3 that produce professional, brand-consistent hero images.

BRAND GUIDELINES:
- Primary palette: #6366F1 (indigo), #4F46E5 (deep indigo), #312E81 (indigo 900)
- Accent: #F59E0B (amber)
- Style: Modern, clean, corporate, professional
- Tone: Innovative, forward-thinking, trustworthy

PROMPT ENGINEERING RULES:
- Always specify: composition, color palette, style, mood, lighting
- Use photorealistic or high-quality digital art styles
- Avoid text in images (DALL-E handles text poorly)
- Optimize for landscape hero banner format (1792x1024)
- Include abstract/conceptual elements representing the article topic
- Reference technology, digital interfaces, innovation imagery
- Keep compositions clean with breathing room for text overlays
- Use depth of field and lighting for visual hierarchy

OUTPUT: A single, detailed DALL-E prompt (150-300 words), self-contained and ready for the API.',
                'capabilities' => ['image_prompts', 'visual_direction', 'brand_consistency'],
                'model_config' => [
                    // LLM used to craft the image prompt
                    'prompt_model' => 'claude-haiku-4-5-20251001',
                    'prompt_provider' => 'anthropic',
                    // Image generation model (ImageManager routes to the correct provider)
                    'generator_model' => 'gpt-image-1',
                    'generator_provider' => 'openai',
                    // Image parameters
                    'size' => '1792x1024',
                    'style' => 'vivid',
                    'quality' => 'standard',
                ],
            ]
        );

        // --- Default Pipeline ---

        ContentPipeline::firstOrCreate(
            ['space_id' => $space->id, 'name' => 'Standard Content Pipeline'],
            [
                'stages' => [
                    [
                        'name' => 'generate',
                        'type' => 'ai_generate',
                        'agent_role' => 'creator',
                    ],
                    [
                        'name' => 'illustrate',
                        'type' => 'ai_illustrate',
                        'agent_role' => 'illustrator',
                        'config' => ['size' => '1792x1024', 'style' => 'vivid'],
                    ],
                    [
                        'name' => 'seo_optimize',
                        'type' => 'ai_transform',
                        'agent_role' => 'optimizer',
                    ],
                    [
                        'name' => 'quality_review',
                        'type' => 'ai_review',
                        'agent_role' => 'reviewer',
                    ],
                    [
                        'name' => 'publish',
                        'type' => 'auto_publish',
                    ],
                ],
                'is_active' => true,
            ]
        );

        $this->command->info("✅ Demo space 'byte5.labs' created with:");
        $this->command->info('   - 3 content types (blog_post, product_description, faq)');
        $this->command->info('   - 5 personas (Tech Writer, Visual Director, SEO Expert, Editorial Director, bytyBot)');
        $this->command->info('   - 1 pipeline (Standard: generate → illustrate → SEO → review → publish)');
    }
}
