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
    // ── Template categories ────────────────────────────────────────────────────

    public function test_registers_template_category(): void
    {
        $this->registry->registerTemplateCategory([
            'slug' => 'podcast',
            'label' => 'Podcast',
            'description' => 'Podcast show notes and transcripts',
            'icon' => '🎙️',
        ]);

        $categories = $this->registry->getTemplateCategories();

        $this->assertCount(1, $categories);
        $this->assertSame('podcast', $categories[0]['slug']);
        $this->assertSame('Podcast', $categories[0]['label']);
        $this->assertSame('🎙️', $categories[0]['icon']);
    }

    public function test_registers_multiple_template_categories(): void
    {
        $this->registry->registerTemplateCategory(['slug' => 'video', 'label' => 'Video']);
        $this->registry->registerTemplateCategory(['slug' => 'podcast', 'label' => 'Podcast']);

        $slugs = $this->registry->getTemplateCategorySlugs();

        $this->assertContains('video', $slugs);
        $this->assertContains('podcast', $slugs);
    }

    public function test_template_category_defaults_null_optional_fields(): void
    {
        $this->registry->registerTemplateCategory([
            'slug' => 'minimal',
            'label' => 'Minimal',
        ]);

        $cat = $this->registry->getTemplateCategories()[0];

        $this->assertNull($cat['description']);
        $this->assertNull($cat['icon']);
    }

    // ── Template packs ─────────────────────────────────────────────────────────

    public function test_registers_template_pack(): void
    {
        $this->registry->registerTemplatePack([
            'id' => 'my-plugin-pack',
            'name' => 'My Plugin Templates',
            'author' => 'TestCo',
            'templates' => [
                ['name' => 'Quick Blog', 'definition' => ['schema_version' => '1.0', 'stages' => []]],
            ],
        ]);

        $packs = $this->registry->getTemplatePacks();

        $this->assertCount(1, $packs);
        $this->assertSame('my-plugin-pack', $packs[0]['id']);
        $this->assertSame('My Plugin Templates', $packs[0]['name']);
        $this->assertSame('TestCo', $packs[0]['author']);
    }

    public function test_get_all_pack_templates_flattens_packs(): void
    {
        $this->registry->registerTemplatePack([
            'id' => 'pack-a',
            'name' => 'Pack A',
            'templates' => [
                ['name' => 'Template 1', 'definition' => []],
                ['name' => 'Template 2', 'definition' => []],
            ],
        ]);
        $this->registry->registerTemplatePack([
            'id' => 'pack-b',
            'name' => 'Pack B',
            'templates' => [
                ['name' => 'Template 3', 'definition' => []],
            ],
        ]);

        $all = $this->registry->getAllPackTemplates();

        $this->assertCount(3, $all);
        $this->assertSame('pack-a', $all[0]['_pack_id']);
        $this->assertSame('pack-b', $all[2]['_pack_id']);
    }

    public function test_template_pack_defaults_null_optional_fields(): void
    {
        $this->registry->registerTemplatePack([
            'id' => 'bare-pack',
            'name' => 'Bare Pack',
            'templates' => [],
        ]);

        $pack = $this->registry->getTemplatePacks()[0];

        $this->assertNull($pack['author']);
        $this->assertNull($pack['url']);
    }
}
