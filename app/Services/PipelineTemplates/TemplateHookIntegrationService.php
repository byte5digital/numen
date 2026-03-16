<?php

namespace App\Services\PipelineTemplates;

use App\Models\Space;
use App\Plugin\HookRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Bridges the HookRegistry plugin hooks with the PipelineTemplateService.
 *
 * Plugins can register template categories and template packs via HookRegistry.
 * This service materialises those registrations into the database at boot time
 * (or on explicit sync) so they appear in the template library UI.
 */
class TemplateHookIntegrationService
{
    public function __construct(
        private readonly HookRegistry $registry,
        private readonly PipelineTemplateService $templateService,
        private readonly TemplateSchemaValidator $validator,
    ) {}

    /**
     * Sync all plugin-registered template packs into the database.
     * Called once during AppServiceProvider boot after plugins are loaded.
     */
    public function syncPackTemplates(Space $space): void
    {
        foreach ($this->registry->getAllPackTemplates() as $def) {
            try {
                $this->upsertPackTemplate($space, $def);
            } catch (\Throwable $e) {
                Log::warning('[TemplateHookIntegration] Failed to sync pack template.', [
                    'pack_id' => $def['_pack_id'] ?? 'unknown',
                    'name' => $def['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Return all categories: built-in defaults merged with plugin-registered.
     *
     * @return array<array{slug: string, label: string, description: string|null, icon: string|null}>
     */
    public function getAvailableCategories(): array
    {
        $builtin = [
            ['slug' => 'blog', 'label' => 'Blog', 'description' => null, 'icon' => null],
            ['slug' => 'social_media', 'label' => 'Social Media', 'description' => null, 'icon' => null],
            ['slug' => 'seo', 'label' => 'SEO', 'description' => null, 'icon' => null],
            ['slug' => 'ecommerce', 'label' => 'E-Commerce', 'description' => null, 'icon' => null],
            ['slug' => 'newsletter', 'label' => 'Newsletter', 'description' => null, 'icon' => null],
            ['slug' => 'technical', 'label' => 'Technical', 'description' => null, 'icon' => null],
            ['slug' => 'custom', 'label' => 'Custom', 'description' => null, 'icon' => null],
        ];

        return array_merge($builtin, $this->registry->getTemplateCategories());
    }

    /**
     * Return all registered template packs.
     *
     * @return array<array{id: string, name: string, templates: array<array<string, mixed>>, author: string|null, url: string|null}>
     */
    public function getTemplatePacks(): array
    {
        return $this->registry->getTemplatePacks();
    }

    /** @param array<string, mixed> $def */
    private function upsertPackTemplate(Space $space, array $def): void
    {
        if (empty($def['name']) || empty($def['definition'])) {
            return;
        }

        // Validate definition before persisting
        /** @var array<string, mixed> $definition */
        $definition = $def['definition'];
        if (! $this->validator->validate($definition)->isValid()) {
            Log::warning('[TemplateHookIntegration] Invalid template definition from pack, skipping.', [
                'name' => $def['name'],
            ]);

            return;
        }

        // Check if a template with this slug already exists for the space
        $slug = $def['slug'] ?? \Illuminate\Support\Str::slug((string) $def['name']);
        $existing = \App\Models\PipelineTemplate::where('space_id', $space->id)
            ->where('slug', $slug)
            ->first();

        if ($existing) {
            return; // Don't overwrite user customisations
        }

        $template = $this->templateService->create($space, [
            'name' => $def['name'],
            'slug' => $slug,
            'description' => $def['description'] ?? null,
            'category' => $def['category'] ?? 'custom',
            'icon' => $def['icon'] ?? null,
            'author_name' => $def['author_name'] ?? null,
            'author_url' => $def['author_url'] ?? null,
        ]);

        $this->templateService->createVersion(
            $template,
            $definition,
            (string) ($def['version'] ?? '1.0.0'),
            'Imported from plugin pack',
        );
    }
}
