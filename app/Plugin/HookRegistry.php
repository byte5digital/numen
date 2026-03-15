<?php

namespace App\Plugin;

use App\Plugin\Contracts\LLMProviderContract;
use App\Plugin\Contracts\PipelineStageContract;
use Closure;
use InvalidArgumentException;

/** Central hook bus for the Numen plugin system. */
class HookRegistry
{
    /** @var array<string, array<Closure>> */
    private array $pipelineStages = [];

    /** @var array<string, class-string<PipelineStageContract>> */
    private array $pipelineStageClasses = [];

    /** @var array<string, Closure> */
    private array $llmProviders = [];

    /** @var array<string, LLMProviderContract> */
    private array $llmProviderInstances = [];

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

    public function registerPipelineStage(string $stageName, Closure $handler): void
    {
        $this->pipelineStages[$stageName][] = $handler;
    }

    /**
     * Register a class-based pipeline stage handler.
     * Handler must implement PipelineStageContract.
     *
     * @param  class-string  $handlerClass
     *
     * @throws InvalidArgumentException
     */
    public function registerPipelineStageClass(string $stageType, string $handlerClass): void
    {
        if (! is_a($handlerClass, PipelineStageContract::class, true)) {
            throw new InvalidArgumentException(
                "Pipeline stage handler [{$handlerClass}] must implement ".PipelineStageContract::class.'.'
            );
        }
        $this->pipelineStageClasses[$stageType] = $handlerClass;
    }

    /** @return array<Closure> */
    public function getPipelineStageHandlers(string $stageName): array
    {
        return $this->pipelineStages[$stageName] ?? [];
    }

    /** @return class-string<PipelineStageContract>|null */
    public function getPipelineStageHandler(string $stageType): ?string
    {
        return $this->pipelineStageClasses[$stageType] ?? null;
    }

    public function hasPipelineStageHandler(string $stageType): bool
    {
        return isset($this->pipelineStageClasses[$stageType]);
    }

    /** @return array<string> */
    public function getRegisteredPipelineStages(): array
    {
        return array_keys($this->pipelineStages);
    }

    /** @return array<string> */
    public function getRegisteredPipelineStageTypes(): array
    {
        return array_keys($this->pipelineStageClasses);
    }

    // ── LLM providers ──────────────────────────────────────────────────────────

    public function registerLLMProvider(string $providerName, Closure $factory): void
    {
        $this->llmProviders[$providerName] = $factory;
    }

    /**
     * Register an LLMProviderContract instance directly.
     * Used by AppServiceProvider after PluginLoader boots.
     *
     * @throws InvalidArgumentException
     */
    public function registerLLMProviderInstance(string $providerName, mixed $provider): void
    {
        if (! ($provider instanceof LLMProviderContract)) {
            throw new InvalidArgumentException(
                "LLM provider [{$providerName}] must implement ".LLMProviderContract::class.'.'
            );
        }
        $this->llmProviderInstances[$providerName] = $provider;
    }

    public function getLLMProviderFactory(string $providerName): ?Closure
    {
        return $this->llmProviders[$providerName] ?? null;
    }

    public function getLLMProviderInstance(string $providerName): ?LLMProviderContract
    {
        return $this->llmProviderInstances[$providerName] ?? null;
    }

    /** @return array<string> */
    public function getRegisteredLLMProviders(): array
    {
        return array_keys($this->llmProviders);
    }

    /**
     * Get all registered LLMProviderContract instances.
     * Used by AppServiceProvider to wire plugin LLM providers into LLMManager.
     *
     * @return array<string, LLMProviderContract>
     */
    public function getLLMProviders(): array
    {
        return $this->llmProviderInstances;
    }

    // ── Image providers ────────────────────────────────────────────────────────

    public function registerImageProvider(string $providerName, Closure $factory): void
    {
        $this->imageProviders[$providerName] = $factory;
    }

    public function getImageProviderFactory(string $providerName): ?Closure
    {
        return $this->imageProviders[$providerName] ?? null;
    }

    /** @return array<string> */
    public function getRegisteredImageProviders(): array
    {
        return array_keys($this->imageProviders);
    }

    // ── Content events ─────────────────────────────────────────────────────────

    public function onContentEvent(string $eventName, Closure $listener): void
    {
        $this->contentEventListeners[$eventName][] = $listener;
    }

    /** @return array<Closure> */
    public function getContentEventListeners(string $eventName): array
    {
        return $this->contentEventListeners[$eventName] ?? [];
    }

    public function dispatchContentEvent(string $eventName, mixed $payload): void
    {
        foreach ($this->getContentEventListeners($eventName) as $listener) {
            $listener($payload);
        }
    }

    /**
     * Fire a content event, passing multiple arguments to each listener.
     * Alias for variadic content event dispatching (used by content hooks).
     */
    public function fireContentEvent(string $event, mixed ...$args): void
    {
        foreach ($this->getContentEventListeners($event) as $listener) {
            $listener(...$args);
        }
    }

    // ── Admin menu items ───────────────────────────────────────────────────────

    /**
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
     * @return array<array{id: string, component: string, props: array<string, mixed>}>
     */
    public function getAdminWidgets(): array
    {
        return $this->adminWidgets;
    }

    // ── Vue components ─────────────────────────────────────────────────────────

    public function registerVueComponent(string $name, string $importPath): void
    {
        $this->vueComponents[$name] = $importPath;
    }

    /** @return array<string, string> */
    public function getVueComponents(): array
    {
        return $this->vueComponents;
    }
}
