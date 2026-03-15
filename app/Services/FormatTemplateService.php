<?php

namespace App\Services;

use App\Models\FormatTemplate;
use Illuminate\Support\Collection;

class FormatTemplateService
{
    /**
     * Resolve the best template for a given space + format.
     * Space-specific first, fall back to global (space_id IS NULL).
     */
    public function getForSpace(int $spaceId, string $formatKey): ?FormatTemplate
    {
        return FormatTemplate::getForSpace($spaceId, $formatKey);
    }

    /**
     * Return all templates applicable to a space — globals merged with space overrides.
     * Space-specific templates shadow any global template with the same format_key.
     *
     * @return Collection<int, FormatTemplate>
     */
    public function getAllForSpace(int $spaceId): Collection
    {
        $templates = FormatTemplate::query()
            ->active()
            ->forSpace($spaceId)
            ->orderByRaw('CASE WHEN space_id IS NULL THEN 1 ELSE 0 END')
            ->get();

        // Deduplicate: space-specific wins over global for the same format_key.
        return $templates->groupBy('format_key')->map(function (Collection $group) use ($spaceId) {
            return $group->firstWhere('space_id', $spaceId) ?? $group->first();
        })->values();
    }

    public function createTemplate(array $data): FormatTemplate
    {
        return FormatTemplate::create($data);
    }

    public function updateTemplate(FormatTemplate $template, array $data): FormatTemplate
    {
        $template->update($data);

        return $template->fresh();
    }

    public function deleteTemplate(FormatTemplate $template): void
    {
        $template->delete();
    }

    /**
     * Seed global default templates for all 8 supported formats.
     * Idempotent — skips creation if any globals already exist.
     */
    public function seedDefaults(): void
    {
        if (FormatTemplate::whereNull('space_id')->exists()) {
            return;
        }

        $defaults = $this->getDefaultTemplates();

        foreach ($defaults as $data) {
            FormatTemplate::create($data);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDefaultTemplates(): array
    {
        return [
            [
                'space_id' => null,
                'format_key' => 'twitter_thread',
                'name' => 'Twitter Thread',
                'description' => 'Converts content into an engaging Twitter/X thread.',
                'system_prompt' => 'You are an expert social media writer specialising in Twitter threads. Write concise, engaging tweets that hook readers and keep them scrolling. Each tweet must be under 280 characters. Number each tweet (1/, 2/, etc.).',
                'user_prompt_template' => "Convert the following article into a Twitter thread of 5–8 tweets.\n\nTitle: {{title}}\nExcerpt: {{excerpt}}\n\nContent:\n{{body}}\n\nTone: {{tone}}\n\nWrite a numbered Twitter thread.",
                'output_schema' => null,
                'max_tokens' => 1024,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'space_id' => null,
                'format_key' => 'linkedin_post',
                'name' => 'LinkedIn Post',
                'description' => 'Transforms content into a professional LinkedIn post.',
                'system_prompt' => 'You are a professional LinkedIn content writer. Craft posts that resonate with a business audience — insightful, credible, and structured with line breaks for readability.',
                'user_prompt_template' => "Write a LinkedIn post based on the following article.\n\nTitle: {{title}}\nExcerpt: {{excerpt}}\n\nContent:\n{{body}}\n\nTone: {{tone}}\nTarget length: {{word_count}} words\n\nWrite a single LinkedIn post.",
                'output_schema' => null,
                'max_tokens' => 800,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'space_id' => null,
                'format_key' => 'newsletter_section',
                'name' => 'Newsletter Section',
                'description' => 'Adapts content into a newsletter-ready section.',
                'system_prompt' => 'You are a newsletter editor. Rewrite the provided article as a compelling newsletter section — engaging intro, key takeaways, and a clear call-to-action.',
                'user_prompt_template' => "Rewrite the following article as a newsletter section.\n\nTitle: {{title}}\nExcerpt: {{excerpt}}\n\nContent:\n{{body}}\n\nTone: {{tone}}\nTarget length: {{word_count}} words\n\nWrite the newsletter section.",
                'output_schema' => null,
                'max_tokens' => 1200,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'space_id' => null,
                'format_key' => 'instagram_caption',
                'name' => 'Instagram Caption',
                'description' => 'Creates an Instagram caption with relevant hashtags.',
                'system_prompt' => 'You are an Instagram content strategist. Write visually evocative captions that inspire engagement. Always end with 5–10 relevant hashtags.',
                'user_prompt_template' => "Write an Instagram caption for the following content.\n\nTitle: {{title}}\nExcerpt: {{excerpt}}\n\nContent:\n{{body}}\n\nTone: {{tone}}\n\nWrite a caption with hashtags.",
                'output_schema' => null,
                'max_tokens' => 500,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'space_id' => null,
                'format_key' => 'podcast_script_outline',
                'name' => 'Podcast Script Outline',
                'description' => 'Generates a structured podcast episode outline.',
                'system_prompt' => 'You are a podcast producer. Convert written content into a structured episode outline: intro hook, main talking points, transitions, and an outro with a call-to-action.',
                'user_prompt_template' => "Create a podcast script outline based on the following article.\n\nTitle: {{title}}\nExcerpt: {{excerpt}}\n\nContent:\n{{body}}\n\nTone: {{tone}}\n\nWrite a detailed podcast episode outline.",
                'output_schema' => null,
                'max_tokens' => 1500,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'space_id' => null,
                'format_key' => 'product_page_copy',
                'name' => 'Product Page Copy',
                'description' => 'Converts content into persuasive product page copy.',
                'system_prompt' => 'You are a conversion copywriter. Transform content into persuasive product-page copy with a headline, benefits-led body, and a strong call-to-action.',
                'user_prompt_template' => "Write product page copy based on the following content.\n\nTitle: {{title}}\nExcerpt: {{excerpt}}\n\nContent:\n{{body}}\n\nTone: {{tone}}\nTarget length: {{word_count}} words\n\nWrite compelling product page copy.",
                'output_schema' => null,
                'max_tokens' => 1000,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'space_id' => null,
                'format_key' => 'faq_section',
                'name' => 'FAQ Section',
                'description' => 'Extracts key questions and answers from content.',
                'system_prompt' => 'You are a content strategist. Extract the most common questions readers would have from the article and write clear, concise answers. Format as Q&A pairs.',
                'user_prompt_template' => "Generate an FAQ section from the following article.\n\nTitle: {{title}}\nExcerpt: {{excerpt}}\n\nContent:\n{{body}}\n\nTone: {{tone}}\n\nWrite 5–8 FAQ pairs in Q: / A: format.",
                'output_schema' => null,
                'max_tokens' => 1200,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'space_id' => null,
                'format_key' => 'youtube_description',
                'name' => 'YouTube Description',
                'description' => 'Creates a SEO-optimised YouTube video description.',
                'system_prompt' => 'You are a YouTube SEO specialist. Write video descriptions that hook viewers in the first two lines, summarise the content, and include timestamps placeholders, relevant keywords, and a call-to-action.',
                'user_prompt_template' => "Write a YouTube video description for the following content.\n\nTitle: {{title}}\nExcerpt: {{excerpt}}\n\nContent:\n{{body}}\n\nTone: {{tone}}\n\nWrite an SEO-optimised YouTube description.",
                'output_schema' => null,
                'max_tokens' => 800,
                'is_default' => true,
                'is_active' => true,
            ],
        ];
    }
}
