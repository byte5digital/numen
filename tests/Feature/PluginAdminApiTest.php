<?php

namespace Tests\Feature;

use App\Models\Plugin;
use App\Models\PluginSetting;
use App\Models\User;
use App\Plugin\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class PluginAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->regularUser = User::factory()->create(['role' => 'editor']);
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function test_admin_can_list_plugins(): void
    {
        Plugin::factory()->count(3)->create();

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/plugins');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'display_name', 'version', 'status'],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_admin_can_show_plugin_details(): void
    {
        $plugin = Plugin::factory()->installed()->create();
        PluginSetting::factory()->for($plugin)->create(['key' => 'api_url']);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/v1/admin/plugins/{$plugin->name}");

        $response->assertOk()
            ->assertJsonPath('data.name', $plugin->name)
            ->assertJsonPath('data.status', 'installed')
            ->assertJsonStructure(['data' => ['settings']]);
    }

    // ── Install ───────────────────────────────────────────────────────────────

    public function test_admin_can_install_plugin(): void
    {
        $plugin = Plugin::factory()->discovered()->create();

        $this->mock(PluginManager::class, function (MockInterface $mock) use ($plugin) {
            $installedPlugin = (clone $plugin);
            $installedPlugin->status = 'installed';
            $installedPlugin->setRelation('settings', collect());

            $mock->shouldReceive('install')
                ->once()
                ->with($plugin->name)
                ->andReturn($installedPlugin);
        });

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/admin/plugins/{$plugin->name}/install");

        $response->assertOk()
            ->assertJsonPath('data.name', $plugin->name);
    }

    // ── Activate ──────────────────────────────────────────────────────────────

    public function test_admin_can_activate_plugin(): void
    {
        $plugin = Plugin::factory()->installed()->create();

        $this->mock(PluginManager::class, function (MockInterface $mock) use ($plugin) {
            $activePlugin = (clone $plugin);
            $activePlugin->status = 'active';
            $activePlugin->setRelation('settings', collect());

            $mock->shouldReceive('activate')
                ->once()
                ->with($plugin->name)
                ->andReturn($activePlugin);
        });

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/admin/plugins/{$plugin->name}/activate");

        $response->assertOk()
            ->assertJsonPath('message', "Plugin [{$plugin->name}] activated successfully.");
    }

    // ── Deactivate ────────────────────────────────────────────────────────────

    public function test_admin_can_deactivate_plugin(): void
    {
        $plugin = Plugin::factory()->active()->create();

        $this->mock(PluginManager::class, function (MockInterface $mock) use ($plugin) {
            $inactivePlugin = (clone $plugin);
            $inactivePlugin->status = 'inactive';
            $inactivePlugin->setRelation('settings', collect());

            $mock->shouldReceive('deactivate')
                ->once()
                ->with($plugin->name)
                ->andReturn($inactivePlugin);
        });

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/admin/plugins/{$plugin->name}/deactivate");

        $response->assertOk()
            ->assertJsonPath('message', "Plugin [{$plugin->name}] deactivated successfully.");
    }

    // ── Uninstall ─────────────────────────────────────────────────────────────

    public function test_admin_can_uninstall_plugin(): void
    {
        $plugin = Plugin::factory()->inactive()->create();

        $this->mock(PluginManager::class, function (MockInterface $mock) use ($plugin) {
            $mock->shouldReceive('uninstall')
                ->once()
                ->with($plugin->name);
        });

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/admin/plugins/{$plugin->name}/uninstall");

        $response->assertOk()
            ->assertJsonPath('message', "Plugin [{$plugin->name}] uninstalled successfully.");
    }

    // ── Update Settings ───────────────────────────────────────────────────────

    public function test_admin_can_update_settings(): void
    {
        $plugin = Plugin::factory()->active()->create();

        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/admin/plugins/{$plugin->name}/settings", [
            'settings' => [
                ['key' => 'api_url', 'value' => 'https://example.com'],
                ['key' => 'timeout', 'value' => 30],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', "Settings for plugin [{$plugin->name}] updated successfully.");

        $this->assertDatabaseHas('plugin_settings', [
            'plugin_id' => $plugin->id,
            'key' => 'api_url',
        ]);
    }

    // ── Access Control ────────────────────────────────────────────────────────

    public function test_non_admin_cannot_manage_plugins(): void
    {
        Plugin::factory()->count(2)->create();

        Sanctum::actingAs($this->regularUser);

        $this->getJson('/api/v1/admin/plugins')->assertStatus(403);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/v1/admin/plugins')->assertUnauthorized();
    }

    // ── Secret Masking ────────────────────────────────────────────────────────

    public function test_secret_settings_are_masked(): void
    {
        $plugin = Plugin::factory()->active()->create();

        PluginSetting::factory()
            ->for($plugin)
            ->secret()
            ->create(['key' => 'api_secret', 'value' => ['data' => 'super-secret-key']]);

        PluginSetting::factory()
            ->for($plugin)
            ->create(['key' => 'api_url', 'value' => ['data' => 'https://api.example.com']]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/v1/admin/plugins/{$plugin->name}");

        $response->assertOk();

        $settings = collect($response->json('data.settings'));

        $secretSetting = $settings->firstWhere('key', 'api_secret');
        $this->assertSame('***', $secretSetting['value'], 'Secret setting value must be masked');

        $publicSetting = $settings->firstWhere('key', 'api_url');
        $this->assertNotSame('***', $publicSetting['value'], 'Public setting must not be masked');
    }
}
