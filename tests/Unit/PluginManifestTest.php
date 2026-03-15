<?php

namespace Tests\Unit;

use App\Plugin\PluginManifest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PluginManifestTest extends TestCase
{
    // ── Valid manifest ────────────────────────────────────────────────────────

    public function test_parses_valid_manifest(): void
    {
        $data = [
            'name' => 'numen-seo',
            'version' => '1.2.3',
            'display_name' => 'SEO Toolkit',
            'provider' => 'Numen\\Plugins\\Seo\\SeoServiceProvider',
            'api_version' => '1.0',
            'description' => 'SEO optimisation tools for Numen.',
            'author' => 'Byte5 GmbH',
            'hooks' => ['pipeline.stage' => ['seo_check']],
            'permissions' => ['seo.manage'],
            'settings_schema' => [['key' => 'keywords', 'type' => 'text']],
        ];

        $manifest = PluginManifest::fromArray($data);

        $this->assertSame('numen-seo', $manifest->name);
        $this->assertSame('1.2.3', $manifest->version);
        $this->assertSame('SEO Toolkit', $manifest->displayName);
        $this->assertSame('Numen\\Plugins\\Seo\\SeoServiceProvider', $manifest->providerClass);
        $this->assertSame('1.0', $manifest->apiVersion);
        $this->assertSame('SEO optimisation tools for Numen.', $manifest->description);
        $this->assertSame('Byte5 GmbH', $manifest->author);
        $this->assertSame(['seo.manage'], $manifest->permissions);
        $this->assertCount(1, $manifest->settingsSchema);
    }

    // ── API version validation ────────────────────────────────────────────────

    public function test_rejects_invalid_api_version(): void
    {
        $manifest = PluginManifest::fromArray([
            'name' => 'numen-test',
            'version' => '1.0.0',
            'display_name' => 'Test Plugin',
            'provider' => 'Numen\\Plugins\\Test\\TestServiceProvider',
            'api_version' => '2.0',   // incompatible major version
        ]);

        // Plugin declares API version 2.0 — must not satisfy ^1.0
        $this->assertFalse(
            $manifest->satisfiesApiVersion('^1.0'),
            'A plugin with api_version 2.0 must not satisfy ^1.0'
        );

        // Must satisfy its own major version range
        $this->assertTrue(
            $manifest->satisfiesApiVersion('^2.0'),
            'A plugin with api_version 2.0 should satisfy ^2.0'
        );
    }

    // ── Required-field validation ─────────────────────────────────────────────

    public function test_rejects_missing_required_fields(): void
    {
        $requiredFields = ['name', 'version', 'display_name', 'provider', 'api_version'];

        $baseData = [
            'name' => 'numen-seo',
            'version' => '1.0.0',
            'display_name' => 'SEO Toolkit',
            'provider' => 'Numen\\Plugins\\Seo\\SeoServiceProvider',
            'api_version' => '1.0',
        ];

        foreach ($requiredFields as $field) {
            $data = $baseData;
            unset($data[$field]);

            $this->expectException(InvalidArgumentException::class);

            try {
                PluginManifest::fromArray($data);
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($field, $e->getMessage());
                throw $e;
            }
        }
    }
}
