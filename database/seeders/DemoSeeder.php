<?php

namespace Database\Seeders;

use App\Models\ContentPipeline;
use App\Models\ContentType;
use App\Models\Persona;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'name'     => 'byte5 Admin',
            'email'    => 'admin@byte5.de',
            'password' => Hash::make('byte5labs'),
            'role'     => 'admin',
        ]);

        $this->command->info("✅ Admin user created: admin@byte5.de / byte5labs");

        // Create demo space
        $space = Space::create([
            'name'     => 'byte5.labs',
            'slug'     => 'byte5-labs',
            'settings' => [
                'brand_guidelines' => 'We are byte5.labs — a forward-thinking AI innovation lab. Our voice is sharp, technical, and bold. We challenge conventional thinking and build the future. No corporate fluff. Substance over style.',
                'default_locale'   => 'en',
                'timezone'         => 'Europe/Berlin',
            ],
        ]);

        // --- Content Types ---

        ContentType::create([
            'space_id'          => $space->id,
            'name'              => 'Blog Post',
            'slug'              => 'blog_post',
            'schema'            => [
                'fields' => [
                    ['name' => 'reading_time_minutes', 'type' => 'integer'],
                    ['name' => 'difficulty', 'type' => 'enum', 'options' => ['beginner', 'intermediate', 'advanced']],
                    ['name' => 'series', 'type' => 'string', 'nullable' => true],
                ],
            ],
            'generation_config' => [
                'default_word_count' => [800, 1500],
                'tone'               => 'professional yet approachable',
                'include_code'       => true,
            ],
        ]);

        ContentType::create([
            'space_id'          => $space->id,
            'name'              => 'Product Description',
            'slug'              => 'product_description',
            'schema'            => [
                'fields' => [
                    ['name' => 'features', 'type' => 'array'],
                    ['name' => 'use_cases', 'type' => 'array'],
                    ['name' => 'pricing_tier', 'type' => 'string', 'nullable' => true],
                ],
            ],
            'generation_config' => [
                'default_word_count' => [200, 500],
                'tone'               => 'concise and compelling',
            ],
        ]);

        ContentType::create([
            'space_id'          => $space->id,
            'name'              => 'FAQ',
            'slug'              => 'faq',
            'schema'            => [
                'fields' => [
                    ['name' => 'questions', 'type' => 'array', 'items' => ['question', 'answer']],
                    ['name' => 'category', 'type' => 'string'],
                ],
            ],
            'generation_config' => [
                'default_word_count' => [500, 1000],
                'tone'               => 'helpful and clear',
            ],
        ]);

        // --- Personas ---

        $creator = Persona::create([
            'space_id'     => $space->id,
            'name'         => 'Tech Writer',
            'role'         => 'creator',
            'system_prompt' => "You are a sharp, insightful tech writer for byte5.labs — an AI innovation lab building the future. Your writing style is:

- **Direct and substantive** — no filler, no corporate speak
- **Technically precise** — you understand the details and convey them clearly
- **Bold and opinionated** — you have a point of view and you back it up
- **Engaging** — you make complex topics accessible without dumbing them down

You write about AI, software engineering, agent systems, and the future of tech. Your audience is developers, tech leads, and CTOs who want depth, not marketing fluff.

Always include practical examples, code snippets where relevant, and concrete takeaways. End with a forward-looking perspective — what does this mean for the future?",
            'capabilities' => ['content_generation', 'code_examples', 'technical_writing'],
            'model_config' => [
                'model'       => 'claude-sonnet-4-6',
                'temperature' => 0.8,
                'max_tokens'  => 8192,
            ],
        ]);

        $seo = Persona::create([
            'space_id'     => $space->id,
            'name'         => 'SEO Specialist',
            'role'         => 'optimizer',
            'system_prompt' => "You are an SEO specialist. Your job is to optimize content for search engines while maintaining readability and quality. You focus on:

- Meta titles and descriptions that drive clicks
- Natural keyword integration (never keyword-stuffing)
- Schema.org structured data for rich snippets
- Internal linking opportunities
- Readability and content structure for featured snippets
- Open Graph and social media optimization

Output structured JSON with your analysis and optimizations. Be data-driven and precise.",
            'capabilities' => ['seo_analysis', 'meta_optimization', 'schema_generation'],
            'model_config' => [
                'model'       => 'claude-haiku-4-5-20251001',
                'temperature' => 0.3,
                'max_tokens'  => 4096,
            ],
        ]);

        $reviewer = Persona::create([
            'space_id'     => $space->id,
            'name'         => 'Editorial Director',
            'role'         => 'reviewer',
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
                'model'       => 'claude-opus-4-6',
                'temperature' => 0.2,
                'max_tokens'  => 4096,
            ],
        ]);

        // --- Default Pipeline ---

        ContentPipeline::create([
            'space_id'  => $space->id,
            'name'      => 'Standard Content Pipeline',
            'stages'    => [
                [
                    'name'       => 'generate',
                    'type'       => 'ai_generate',
                    'agent_role' => 'creator',
                ],
                [
                    'name'       => 'illustrate',
                    'type'       => 'ai_illustrate',
                    'agent_role' => 'illustrator',
                    'config'     => ['size' => '1792x1024', 'style' => 'vivid'],
                ],
                [
                    'name'       => 'seo_optimize',
                    'type'       => 'ai_transform',
                    'agent_role' => 'optimizer',
                ],
                [
                    'name'       => 'quality_review',
                    'type'       => 'ai_review',
                    'agent_role' => 'reviewer',
                ],
                [
                    'name' => 'publish',
                    'type' => 'auto_publish',
                ],
            ],
            'is_active' => true,
        ]);

        $this->command->info("✅ Demo space 'byte5.labs' created with:");
        $this->command->info("   - 3 content types (blog_post, product_description, faq)");
        $this->command->info("   - 3 personas (Tech Writer, SEO Specialist, Editorial Director)");
        $this->command->info("   - 1 pipeline (Standard: generate → SEO → review → publish)");
    }
}
