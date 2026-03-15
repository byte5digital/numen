<?php

namespace Tests\Unit;

use App\Plugin\HookRegistry;
use PHPUnit\Framework\TestCase;

class HookRegistryTest extends TestCase
{
    private HookRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new HookRegistry;
    }

    // ── Pipeline stages ───────────────────────────────────────────────────────

    public function test_registers_pipeline_stage(): void
    {
        $called = false;
        $handler = static function ($payload) use (&$called): void {
            $called = true;
        };

        $this->registry->registerPipelineStage('seo_check', $handler);

        $handlers = $this->registry->getPipelineStageHandlers('seo_check');

        $this->assertCount(1, $handlers);

        // Invoke the handler to verify it's the one we registered
        ($handlers[0])([]);
        $this->assertTrue($called, 'Registered pipeline stage handler should be callable');
    }

    public function test_multiple_handlers_for_same_stage(): void
    {
        $log = [];
        $this->registry->registerPipelineStage('review', static function () use (&$log): void {
            $log[] = 'first';
        });
        $this->registry->registerPipelineStage('review', static function () use (&$log): void {
            $log[] = 'second';
        });

        $this->assertCount(2, $this->registry->getPipelineStageHandlers('review'));
    }

    // ── LLM providers ─────────────────────────────────────────────────────────

    public function test_registers_llm_provider(): void
    {
        $factory = static fn () => new \stdClass;

        $this->registry->registerLLMProvider('my-llm', $factory);

        $registered = $this->registry->getRegisteredLLMProviders();

        $this->assertContains('my-llm', $registered);
        $this->assertSame($factory, $this->registry->getLLMProviderFactory('my-llm'));
    }

    public function test_unknown_llm_provider_factory_returns_null(): void
    {
        $this->assertNull($this->registry->getLLMProviderFactory('nonexistent'));
    }

    // ── Content events ────────────────────────────────────────────────────────

    public function test_fires_content_events(): void
    {
        $received = [];

        $this->registry->onContentEvent('content.created', static function ($payload) use (&$received): void {
            $received[] = $payload;
        });

        $this->registry->onContentEvent('content.created', static function ($payload) use (&$received): void {
            $received[] = 'listener-2:'.$payload;
        });

        $this->registry->fireContentEvent('content.created', 'article-123');

        $this->assertCount(2, $received);
        $this->assertSame('article-123', $received[0]);
        $this->assertSame('listener-2:article-123', $received[1]);
    }

    public function test_fires_content_event_with_dispatch(): void
    {
        $received = null;
        $this->registry->onContentEvent('content.updated', static function ($payload) use (&$received): void {
            $received = $payload;
        });

        $this->registry->dispatchContentEvent('content.updated', ['id' => 42]);

        $this->assertSame(['id' => 42], $received);
    }

    public function test_no_listeners_does_not_throw(): void
    {
        // Should silently do nothing
        $this->registry->fireContentEvent('content.deleted', 'payload');
        $this->assertTrue(true, 'Firing event with no listeners should not throw');
    }

    // ── Vue components ────────────────────────────────────────────────────────

    public function test_registers_vue_components(): void
    {
        $this->registry->registerVueComponent('PluginWidget', '@/plugins/seo/PluginWidget.vue');
        $this->registry->registerVueComponent('PluginSettings', '@/plugins/seo/PluginSettings.vue');

        $components = $this->registry->getVueComponents();

        $this->assertArrayHasKey('PluginWidget', $components);
        $this->assertSame('@/plugins/seo/PluginWidget.vue', $components['PluginWidget']);
        $this->assertArrayHasKey('PluginSettings', $components);
        $this->assertCount(2, $components);
    }

    public function test_registering_same_vue_component_overwrites(): void
    {
        $this->registry->registerVueComponent('Widget', '@/v1/Widget.vue');
        $this->registry->registerVueComponent('Widget', '@/v2/Widget.vue');

        $components = $this->registry->getVueComponents();

        $this->assertSame('@/v2/Widget.vue', $components['Widget']);
        $this->assertCount(1, $components);
    }
}
