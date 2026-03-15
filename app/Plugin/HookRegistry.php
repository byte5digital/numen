<?php

namespace App\Plugin;

use Closure;

/**
 * Central hook bus for the Numen plugin system.
 *
 * Plugins register their extensions here; the core calls them at the
 * appropriate extension points.
 */
class HookRegistry
{
    /** @var array<string, array<Closure>> */
    private array $pipelineStages = [];

    /** @var array<string, Closure> */
    private array $llmProviders = [];

    /** @var array<string, Closure> */
    private array $imageProviders = [];

    /** @var array<string, array<Closure>> */
    private array $contentEventListeners = [];

    /** @var array<array{id: string, label: string, route: string, icon: string|null, weight: int}> */
    private array $adminMenuItems = [];

    /** @var array<array{id: string, component: string, props: array<string, mixed>}> */
    private array $adminWidgets = [];

    /** @var array<string, string> */
    private array $vueComponents = [];

    // ── Pipeline stages ────────────────────────────────────────────────────────

    /**
     * Register a named pipeline stage processor.
     *
     * The Closure receives (array $context, array $stageConfig): array $context.
     */
    public function registerPipelineStage(string $stageName, Closure $handler): void
    {
        $this->pipelineStages[$stageName][] = $handler;
    }

    /**
     * Get all handlers registered for a pipeline stage name.
     *
     * @return array<Closure>
     */
    public function getPipelineStageHandlers(string $stageName): array
    {
        return $this->pipelineStages[$stageName] ?? [];
    }

    /**
     * Get all registered pipeline stage names.
     *
     * @return array<string>
     */
    public function getRegisteredPipelineStages(): array
    {
        return array_keys($this->pipelineStages);
    }

    // ── LLM providers ──────────────────────────────────────────────────────────

    /**
     * Register a custom LLM provider factory.
     *
     * The Closure receives (array $config): LLMProviderInterface.
     */
    public function registerLLMProvider(string $providerName, Closure $factory): void
    {
        $this->llmProviders[$providerName] = $factory;
    }

    /**
     * Get the factory for an LLM provider, or null if not registered.
     */
    public function getLLMProviderFactory(string $providerName): ?Closure
    {
        return $this->llmProviders[$providerName] ?? null;
    }

    /**
     * Get all registered LLM provider names.
     *
     * @return array<string>
     */
    public function getRegisteredLLMProviders(): array
    {
        return array_keys($this->llmProviders);
    }

    // ── Image providers ────────────────────────────────────────────────────────

    /**
     * Register a custom image generation provider factory.
     *
     * The Closure receives (array $config): ImageProviderInterface.
     */
    public function registerImageProvider(string $providerName, Closure $factory): void
    {
        $this->imageProviders[$providerName] = $factory;
    }

    /**
     * Get the factory for an image provider, or null if not registered.
     */
    public function getImageProviderFactory(string $providerName): ?Closure
    {
        return $this->imageProviders[$providerName] ?? null;
    }

    /**
     * Get all registered image provider names.
     *
     * @return array<string>
     */
    public function getRegisteredImageProviders(): array
    {
        return array_keys($this->imageProviders);
    }

    // ── Content events ─────────────────────────────────────────────────────────

    /**
     * Listen to a content lifecycle event.
     *
     * $eventName examples: 'content.created', 'content.published', 'content.deleted'
     * The Closure receives (mixed $payload): void.
     */
    public function onContentEvent(string $eventName, Closure $listener): void
    {
        $this->contentEventListeners[$eventName][] = $listener;
    }

    /**
     * Get all listeners for a content event.
     *
     * @return array<Closure>
     */
    public function getContentEventListeners(string $eventName): array
    {
        return $this->contentEventListeners[$eventName] ?? [];
    }

    /**
     * Dispatch a content event to all registered listeners.
     */
    public function dispatchContentEvent(string $eventName, mixed $payload): void
    {
        foreach ($this->getContentEventListeners($eventName) as $listener) {
            $listener($payload);
        }
    }

    // ── Admin menu items ───────────────────────────────────────────────────────

    /**
     * Add an item to the admin navigation menu.
     *
     * @param  array{id: string, label: string, route: string, icon?: string|null, weight?: int}  $item
     */
    public function addAdminMenuItem(array $item): void
    {
        $this->adminMenuItems[] = [
            'id' => $item['id'],
            'label' => $item['label'],
            'route' => $item['route'],
            'icon' => $item['icon'] ?? null,
            'weight' => $item['weight'] ?? 100,
        ];
    }

    /**
     * Get all registered admin menu items, sorted by weight.
     *
     * @return array<array{id: string, label: string, route: string, icon: string|null, weight: int}>
     */
    public function getAdminMenuItems(): array
    {
        $items = $this->adminMenuItems;
        usort($items, fn ($a, $b) => $a['weight'] <=> $b['weight']);

        return $items;
    }

    // ── Admin widgets ──────────────────────────────────────────────────────────

    /**
     * Add a Vue widget to the admin dashboard.
     *
     * @param  array{id: string, component: string, props?: array<string, mixed>}  $widget
     */
    public function addAdminWidget(array $widget): void
    {
        $this->adminWidgets[] = [
            'id' => $widget['id'],
            'component' => $widget['component'],
            'props' => $widget['props'] ?? [],
        ];
    }

    /**
     * Get all registered admin dashboard widgets.
     *
     * @return array<array{id: string, component: string, props: array<string, mixed>}>
     */
    public function getAdminWidgets(): array
    {
        return $this->adminWidgets;
    }

    // ── Vue components ─────────────────────────────────────────────────────────

    /**
     * Register a Vue component that should be globally available in the frontend.
     *
     * @param  string  $name  Vue component name (e.g. 'MyPluginWidget')
     * @param  string  $importPath  Absolute path or NPM package reference to the .vue file
     */
    public function registerVueComponent(string $name, string $importPath): void
    {
        $this->vueComponents[$name] = $importPath;
    }

    /**
     * Get all registered Vue component definitions.
     *
     * @return array<string, string>
     */
    public function getVueComponents(): array
    {
        return $this->vueComponents;
    }
}
