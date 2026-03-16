<?php

namespace Database\Seeders;

use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateVersion;
use Illuminate\Database\Seeder;

class BuiltInTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $spec) {
            $exists = PipelineTemplate::withTrashed()
                ->where('slug', $spec['slug'])
                ->whereNull('space_id')
                ->exists();

            if ($exists) {
                continue;
            }

            $template = PipelineTemplate::create([
                'space_id' => null,
                'name' => $spec['name'],
                'slug' => $spec['slug'],
                'description' => $spec['description'],
                'category' => $spec['category'],
                'icon' => $spec['icon'],
                'schema_version' => '1.0',
                'is_published' => true,
                'author_name' => 'Numen',
                'author_url' => 'https://numen.ai',
            ]);

            PipelineTemplateVersion::create([
                'template_id' => $template->id,
                'version' => '1.0.0',
                'definition' => $spec['definition'],
                'changelog' => 'Initial built-in template.',
                'is_latest' => true,
                'published_at' => now(),
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function templates(): array
    {
        return [
            $this->blogPostPipeline(),
            $this->socialMediaCampaign(),
            $this->productDescription(),
            $this->emailNewsletter(),
            $this->pressRelease(),
            $this->landingPage(),
            $this->technicalDocumentation(),
            $this->videoScript(),
        ];
    }

    /** @return array<string, mixed> */
    private function blogPostPipeline(): array
    {
        return [
            'name' => 'Blog Post Pipeline',
            'slug' => 'blog-post-pipeline',
            'description' => 'Full blog post creation pipeline: outline, draft, SEO review, human gate, publish.',
            'category' => 'content',
            'icon' => 'pencil',
            'definition' => [
                'version' => '1.0',
                'personas' => [
                    ['ref' => 'blog_writer', 'name' => 'Blog Writer', 'system_prompt' => 'You are an expert blog writer producing engaging, well-structured long-form content.', 'llm_provider' => 'openai', 'llm_model' => 'gpt-4o', 'voice_guidelines' => 'Conversational, informative, SEO-aware.'],
                    ['ref' => 'seo_reviewer', 'name' => 'SEO Reviewer', 'system_prompt' => 'You are an SEO specialist who reviews content for keyword density, meta descriptions, and readability.', 'llm_provider' => 'openai', 'llm_model' => 'gpt-4o-mini'],
                ],
                'stages' => [
                    ['type' => 'ai_generate', 'name' => 'Outline', 'persona_ref' => 'blog_writer', 'config' => ['prompt_template' => 'Create a detailed outline for a blog post about {topic}.'], 'enabled' => true],
                    ['type' => 'ai_generate', 'name' => 'Draft', 'persona_ref' => 'blog_writer', 'config' => ['prompt_template' => 'Write a full blog post based on this outline: {outline}'], 'enabled' => true],
                    ['type' => 'ai_review', 'name' => 'SEO Check', 'persona_ref' => 'seo_reviewer', 'config' => ['prompt_template' => 'Review this blog post for SEO quality: {draft}'], 'enabled' => true],
                    ['type' => 'human_gate', 'name' => 'Editor Approval', 'config' => ['instructions' => 'Review and approve the blog post before publishing.'], 'enabled' => true],
                    ['type' => 'auto_publish', 'name' => 'Publish', 'config' => ['target' => 'cms'], 'enabled' => true],
                ],
                'settings' => ['auto_publish' => false, 'review_required' => true, 'max_retries' => 3, 'timeout_seconds' => 300],
                'variables' => [
                    ['key' => 'topic', 'type' => 'string', 'label' => 'Blog Topic', 'required' => true],
                    ['key' => 'keywords', 'type' => 'text', 'label' => 'Target Keywords', 'required' => false],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function socialMediaCampaign(): array
    {
        return [
            'name' => 'Social Media Campaign',
            'slug' => 'social-media-campaign',
            'description' => 'Generate platform-specific social media posts from a single campaign brief.',
            'category' => 'social',
            'icon' => 'megaphone',
            'definition' => [
                'version' => '1.0',
                'personas' => [
                    ['ref' => 'social_copywriter', 'name' => 'Social Copywriter', 'system_prompt' => 'You are a social media expert who crafts engaging, platform-native copy.', 'llm_provider' => 'openai', 'llm_model' => 'gpt-4o', 'voice_guidelines' => 'Punchy, trend-aware, emoji-friendly.'],
                ],
                'stages' => [
                    ['type' => 'ai_generate', 'name' => 'Twitter Post', 'persona_ref' => 'social_copywriter', 'config' => ['prompt_template' => 'Write a Twitter post (max 280 chars) for: {campaign_brief}'], 'enabled' => true],
                    ['type' => 'ai_generate', 'name' => 'LinkedIn Post', 'persona_ref' => 'social_copywriter', 'config' => ['prompt_template' => 'Write a professional LinkedIn post for: {campaign_brief}'], 'enabled' => true],
                    ['type' => 'ai_generate', 'name' => 'Instagram Caption', 'persona_ref' => 'social_copywriter', 'config' => ['prompt_template' => 'Write an Instagram caption with hashtags for: {campaign_brief}'], 'enabled' => true],
                    ['type' => 'ai_review', 'name' => 'Brand Voice Check', 'persona_ref' => 'social_copywriter', 'config' => ['prompt_template' => 'Check the following posts for brand consistency: {posts}'], 'enabled' => true],
                ],
                'settings' => ['auto_publish' => false, 'review_required' => true, 'max_retries' => 2, 'timeout_seconds' => 180],
                'variables' => [
                    ['key' => 'campaign_brief', 'type' => 'text', 'label' => 'Campaign Brief', 'required' => true],
                    ['key' => 'brand_tone', 'type' => 'select', 'label' => 'Brand Tone', 'required' => false, 'options' => ['professional', 'playful', 'inspirational', 'edgy']],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function productDescription(): array
    {
        return [
            'name' => 'Product Description',
            'slug' => 'product-description',
            'description' => 'Generate compelling product descriptions with feature bullets and SEO optimization.',
            'category' => 'ecommerce',
            'icon' => 'cart',
            'definition' => [
                'version' => '1.0',
                'personas' => [
                    ['ref' => 'product_copywriter', 'name' => 'Product Copywriter', 'system_prompt' => 'You write compelling product descriptions that convert browsers into buyers.', 'llm_provider' => 'openai', 'llm_model' => 'gpt-4o', 'voice_guidelines' => 'Benefit-led, clear, persuasive.'],
                ],
                'stages' => [
                    ['type' => 'ai_generate', 'name' => 'Long Description', 'persona_ref' => 'product_copywriter', 'config' => ['prompt_template' => 'Write a detailed product description for {product_name}: {product_details}'], 'enabled' => true],
                    ['type' => 'ai_transform', 'name' => 'Feature Bullets', 'persona_ref' => 'product_copywriter', 'config' => ['prompt_template' => 'Extract 5 key feature bullets from: {long_description}'], 'enabled' => true],
                    ['type' => 'ai_transform', 'name' => 'Meta Description', 'persona_ref' => 'product_copywriter', 'config' => ['prompt_template' => 'Write a 160-char SEO meta description for: {long_description}'], 'enabled' => true],
                ],
                'settings' => ['auto_publish' => true, 'review_required' => false, 'max_retries' => 3, 'timeout_seconds' => 120],
                'variables' => [
                    ['key' => 'product_name', 'type' => 'string', 'label' => 'Product Name', 'required' => true],
                    ['key' => 'product_details', 'type' => 'text', 'label' => 'Product Details', 'required' => true],
                    ['key' => 'target_audience', 'type' => 'string', 'label' => 'Target Audience', 'required' => false],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function emailNewsletter(): array
    {
        return [
            'name' => 'Email Newsletter',
            'slug' => 'email-newsletter',
            'description' => 'Create full email newsletters with subject line variants and CTA optimisation.',
            'category' => 'email',
            'icon' => 'email',
            'definition' => [
                'version' => '1.0',
                'personas' => [
                    ['ref' => 'email_writer', 'name' => 'Email Writer', 'system_prompt' => 'You are an expert email marketer who writes newsletters people actually read.', 'llm_provider' => 'openai', 'llm_model' => 'gpt-4o', 'voice_guidelines' => 'Warm, direct, action-oriented.'],
                ],
                'stages' => [
                    ['type' => 'ai_generate', 'name' => 'Subject Lines', 'persona_ref' => 'email_writer', 'config' => ['prompt_template' => 'Write 5 email subject line variants for a newsletter about: {newsletter_topic}'], 'enabled' => true],
                    ['type' => 'ai_generate', 'name' => 'Newsletter Body', 'persona_ref' => 'email_writer', 'config' => ['prompt_template' => 'Write a newsletter body for: {newsletter_topic}. Include intro, 3 sections, and a CTA.'], 'enabled' => true],
                    ['type' => 'ai_review', 'name' => 'Spam Check', 'persona_ref' => 'email_writer', 'config' => ['prompt_template' => 'Review this email for spam triggers and deliverability issues: {body}'], 'enabled' => true],
                    ['type' => 'human_gate', 'name' => 'Final Approval', 'config' => ['instructions' => 'Review and approve the newsletter before sending.'], 'enabled' => true],
                ],
                'settings' => ['auto_publish' => false, 'review_required' => true, 'max_retries' => 2, 'timeout_seconds' => 240],
                'variables' => [
                    ['key' => 'newsletter_topic', 'type' => 'string', 'label' => 'Newsletter Topic', 'required' => true],
                    ['key' => 'send_date', 'type' => 'string', 'label' => 'Planned Send Date', 'required' => false],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function pressRelease(): array
    {
        return [
            'name' => 'Press Release',
            'slug' => 'press-release',
            'description' => 'Generate professional press releases with datelines, quotes, and boilerplate.',
            'category' => 'pr',
            'icon' => 'newspaper',
            'definition' => [
                'version' => '1.0',
                'personas' => [
                    ['ref' => 'pr_writer', 'name' => 'PR Writer', 'system_prompt' => 'You are a public relations professional who writes clear, factual press releases in AP style.', 'llm_provider' => 'openai', 'llm_model' => 'gpt-4o', 'voice_guidelines' => 'Formal, objective, newswire-ready.'],
                    ['ref' => 'legal_reviewer', 'name' => 'Legal Reviewer', 'system_prompt' => 'You review press releases for legal risks, false claims, and compliance issues.', 'llm_provider' => 'openai', 'llm_model' => 'gpt-4o-mini'],
                ],
                'stages' => [
                    ['type' => 'ai_generate', 'name' => 'Draft Press Release', 'persona_ref' => 'pr_writer', 'config' => ['prompt_template' => 'Write a press release for: {announcement}. Company: {company_name}. Contact: {contact_info}'], 'enabled' => true],
                    ['type' => 'ai_review', 'name' => 'Legal Review', 'persona_ref' => 'legal_reviewer', 'config' => ['prompt_template' => 'Review this press release for legal risks: {draft}'], 'enabled' => true],
                    ['type' => 'human_gate', 'name' => 'Executive Sign-off', 'config' => ['instructions' => 'Route to executive for approval before distribution.'], 'enabled' => true],
                ],
                'settings' => ['auto_publish' => false, 'review_required' => true, 'max_retries' => 2, 'timeout_seconds' => 300],
                'variables' => [
                    ['key' => 'announcement', 'type' => 'text', 'label' => 'Announcement Details', 'required' => true],
                    ['key' => 'company_name', 'type' => 'string', 'label' => 'Company Name', 'required' => true],
                    ['key' => 'contact_info', 'type' => 'text', 'label' => 'Media Contact Info', 'required' => true],
                    ['key' => 'embargo_date', 'type' => 'string', 'label' => 'Embargo Date', 'required' => false],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function landingPage(): array
    {
        return [
            'name' => 'Landing Page',
            'slug' => 'landing-page',
            'description' => 'Craft high-converting landing page copy: headline, hero, benefits, social proof, CTA.',
            'category' => 'marketing',
            'icon' => 'rocket',
            'definition' => [
                'version' => '1.0',
                'personas' => [
                    ['ref' => 'conversion_copywriter', 'name' => 'Conversion Copywriter', 'system_prompt' => 'You are a direct-response copywriter specialising in high-converting landing pages.', 'llm_provider' => 'openai', 'llm_model' => 'gpt-4o', 'voice_guidelines' => 'Benefit-driven, urgent, customer-centric.'],
                ],
                'stages' => [
                    ['type' => 'ai_generate', 'name' => 'Headline Variants', 'persona_ref' => 'conversion_copywriter', 'config' => ['prompt_template' => 'Write 5 headline variants for a landing page about {offer}.'], 'enabled' => true],
                    ['type' => 'ai_generate', 'name' => 'Hero Section', 'persona_ref' => 'conversion_copywriter', 'config' => ['prompt_template' => 'Write hero copy (headline, subheadline, CTA) for: {offer}'], 'enabled' => true],
                    ['type' => 'ai_generate', 'name' => 'Benefits Section', 'persona_ref' => 'conversion_copywriter', 'config' => ['prompt_template' => 'Write 6 key benefits for: {offer}. Target audience: {target_audience}'], 'enabled' => true],
                    ['type' => 'ai_generate', 'name' => 'CTA Copy', 'persona_ref' => 'conversion_copywriter', 'config' => ['prompt_template' => 'Write 3 CTA button text variants and supporting copy for: {offer}'], 'enabled' => true],
                    ['type' => 'ai_review', 'name' => 'Conversion Review', 'persona_ref' => 'conversion_copywriter', 'config' => ['prompt_template' => 'Review the landing page copy for CRO: {full_copy}'], 'enabled' => true],
                ],
                'settings' => ['auto_publish' => false, 'review_required' => true, 'max_retries' => 3, 'timeout_seconds' => 360],
                'variables' => [
                    ['key' => 'offer', 'type' => 'text', 'label' => 'Offer / Product', 'required' => true],
                    ['key' => 'target_audience', 'type' => 'text', 'label' => 'Target Audience', 'required' => true],
                    ['key' => 'unique_value', 'type' => 'text', 'label' => 'Unique Value Prop', 'required' => false],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function technicalDocumentation(): array
    {
        return [
            'name' => 'Technical Documentation',
            'slug' => 'technical-documentation',
            'description' => 'Generate developer-ready technical documentation: API refs, guides, and FAQs.',
            'category' => 'technical',
            'icon' => 'books',
            'definition' => [
                'version' => '1.0',
                'personas' => [
                    ['ref' => 'technical_writer', 'name' => 'Technical Writer', 'system_prompt' => 'You are a technical writer who creates clear, accurate developer documentation.', 'llm_provider' => 'openai', 'llm_model' => 'gpt-4o', 'voice_guidelines' => 'Precise, scannable, code-inclusive.'],
                ],
                'stages' => [
                    ['type' => 'ai_generate', 'name' => 'Overview Section', 'persona_ref' => 'technical_writer', 'config' => ['prompt_template' => 'Write an overview section for documentation of {feature_name}.'], 'enabled' => true],
                    ['type' => 'ai_generate', 'name' => 'Getting Started', 'persona_ref' => 'technical_writer', 'config' => ['prompt_template' => 'Write a Getting Started guide for {feature_name}. Include code examples.'], 'enabled' => true],
                    ['type' => 'ai_generate', 'name' => 'API Reference', 'persona_ref' => 'technical_writer', 'config' => ['prompt_template' => 'Document the following API endpoints in OpenAPI style: {api_spec}'], 'enabled' => true],
                    ['type' => 'ai_generate', 'name' => 'FAQ Section', 'persona_ref' => 'technical_writer', 'config' => ['prompt_template' => 'Write an FAQ section for {feature_name} based on common developer questions.'], 'enabled' => true],
                    ['type' => 'ai_review', 'name' => 'Accuracy Review', 'persona_ref' => 'technical_writer', 'config' => ['prompt_template' => 'Review the following documentation for technical accuracy and completeness: {draft}'], 'enabled' => true],
                    ['type' => 'human_gate', 'name' => 'Engineer Review', 'config' => ['instructions' => 'Have an engineer verify technical accuracy.'], 'enabled' => true],
                ],
                'settings' => ['auto_publish' => false, 'review_required' => true, 'max_retries' => 3, 'timeout_seconds' => 480],
                'variables' => [
                    ['key' => 'feature_name', 'type' => 'string', 'label' => 'Feature / Product Name', 'required' => true],
                    ['key' => 'api_spec', 'type' => 'text', 'label' => 'API Spec / Endpoints', 'required' => false],
                    ['key' => 'audience_level', 'type' => 'select', 'label' => 'Audience Level', 'required' => false, 'options' => ['beginner', 'intermediate', 'advanced']],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function videoScript(): array
    {
        return [
            'name' => 'Video Script',
            'slug' => 'video-script',
            'description' => 'Generate full video scripts with hook, scene breakdown, narration, and call-to-action.',
            'category' => 'video',
            'icon' => 'film',
            'definition' => [
                'version' => '1.0',
                'personas' => [
                    ['ref' => 'script_writer', 'name' => 'Script Writer', 'system_prompt' => 'You write compelling video scripts for YouTube, explainer videos, and ads.', 'llm_provider' => 'openai', 'llm_model' => 'gpt-4o', 'voice_guidelines' => 'Engaging, visual, paced for on-camera delivery.'],
                ],
                'stages' => [
                    ['type' => 'ai_generate', 'name' => 'Hook', 'persona_ref' => 'script_writer', 'config' => ['prompt_template' => 'Write a compelling 5-second hook for a video about: {video_topic}'], 'enabled' => true],
                    ['type' => 'ai_generate', 'name' => 'Scene Breakdown', 'persona_ref' => 'script_writer', 'config' => ['prompt_template' => 'Create a scene-by-scene breakdown for a {video_length}-minute video about: {video_topic}'], 'enabled' => true],
                    ['type' => 'ai_generate', 'name' => 'Full Script', 'persona_ref' => 'script_writer', 'config' => ['prompt_template' => 'Write the full narration script. Topic: {video_topic}. Scenes: {scene_breakdown}'], 'enabled' => true],
                    ['type' => 'ai_review', 'name' => 'Pacing Review', 'persona_ref' => 'script_writer', 'config' => ['prompt_template' => 'Review this script for pacing, clarity, and engagement: {full_script}'], 'enabled' => true],
                    ['type' => 'human_gate', 'name' => 'Director Sign-off', 'config' => ['instructions' => 'Have the director review and approve the script.'], 'enabled' => true],
                ],
                'settings' => ['auto_publish' => false, 'review_required' => true, 'max_retries' => 3, 'timeout_seconds' => 360],
                'variables' => [
                    ['key' => 'video_topic', 'type' => 'string', 'label' => 'Video Topic', 'required' => true],
                    ['key' => 'video_length', 'type' => 'select', 'label' => 'Video Length (min)', 'required' => true, 'options' => ['1', '2', '3', '5', '10', '15']],
                    ['key' => 'video_style', 'type' => 'select', 'label' => 'Video Style', 'required' => false, 'options' => ['explainer', 'tutorial', 'testimonial', 'ad', 'documentary']],
                ],
            ],
        ];
    }
}
