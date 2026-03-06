<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Generates optimized DALL-E 3 prompts from content metadata.
 * Uses Anthropic Haiku for cheap/fast prompt crafting.
 */
class ImagePromptBuilder
{
    public function build(string $title, ?string $excerpt = null, array $tags = [], string $contentType = 'blog_post'): string
    {
        $llm = app(LLMManager::class);

        $tagList = implode(', ', $tags);

        $messages = [
            [
                'role' => 'user',
                'content' => "Generate a single DALL-E 3 image prompt for a hero banner image.\n\n".
                    "Content title: {$title}\n".
                    "Content type: {$contentType}\n".
                    ($excerpt ? "Excerpt: {$excerpt}\n" : '').
                    ($tagList ? "Tags: {$tagList}\n" : '').
                    "\nRequirements:\n".
                    "- Professional blog hero image style\n".
                    "- Modern, clean, corporate blue color palette (#1E9BD7, #004B73)\n".
                    "- Abstract/conceptual visualization — no text overlays\n".
                    "- Suitable as a wide landscape banner (1792x1024)\n".
                    "- Photorealistic or high-quality digital art\n".
                    "\nRespond with ONLY the prompt text, nothing else. No quotes, no explanation.",
            ],
        ];

        try {
            $response = $llm->complete([
                'model' => config('numen.models.classification', 'claude-haiku-4-5-20251001'),
                'system' => 'You are an expert at crafting DALL-E 3 image generation prompts. You create vivid, detailed prompts that produce stunning professional imagery. Always output just the prompt — no wrapping, no explanation.',
                'messages' => $messages,
                'max_tokens' => 300,
                'temperature' => 0.8,
                '_purpose' => 'image_prompt_generation',
            ]);

            $prompt = trim($response->content);

            if (empty($prompt)) {
                return $this->fallbackPrompt($title, $contentType);
            }

            return $prompt;
        } catch (\Throwable $e) {
            Log::warning('ImagePromptBuilder: LLM call failed, using fallback', [
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackPrompt($title, $contentType);
        }
    }

    private function fallbackPrompt(string $title, string $contentType): string
    {
        return "Professional blog hero image for a technology article about {$title}. ".
            'Modern, clean, corporate blue color palette (#1E9BD7, #004B73). '.
            'Abstract, conceptual visualization. No text overlays. '.
            'High-quality digital art, suitable as a wide landscape banner.';
    }
}
