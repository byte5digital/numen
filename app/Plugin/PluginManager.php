<?php

namespace App\Plugin;

use App\Events\Plugin\PluginActivated;
use App\Events\Plugin\PluginDeactivated;
use App\Events\Plugin\PluginInstalled;
use App\Events\Plugin\PluginUninstalled;
use App\Models\Plugin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Manages the full plugin lifecycle: discover → install → activate → deactivate → uninstall.
 *
 * Each state transition persists to the `plugins` DB table and fires a domain event.
 */
class PluginManager
{
    public function __construct(
        private readonly PluginLoader $loader,
    ) {}

    // ── Discovery ──────────────────────────────────────────────────────────────

    /**
     * Scan all configured plugin paths and upsert DB records with status=discovered.
     * Already-installed / active plugins retain their current status.
     *
     * @return array<Plugin> Newly upserted Plugin models.
     */
    public function discover(): array
    {
        $manifests = $this->loader->getLoaded();

        // Re-trigger discovery if loader hasn't booted yet (e.g. CLI context)
        if (empty($manifests)) {
            $this->loader->boot();
            $manifests = $this->loader->getLoaded();
        }

        $upserted = [];

        foreach ($manifests as $manifest) {
            /** @var Plugin $plugin */
            $plugin = Plugin::withTrashed()->firstOrNew(['name' => $manifest->name]);

            // Restore soft-deleted plugins on re-discovery
            if ($plugin->trashed()) {
                $plugin->restore();
            }

            // Only update status to discovered if not yet installed
            if (! $plugin->exists || $plugin->status === 'discovered') {
                $plugin->status = 'discovered';
            }

            $plugin->fill([
                'id' => $plugin->id ?? (string) Str::ulid(),
                'display_name' => $manifest->displayName,
                'version' => $manifest->version,
                'description' => $manifest->description,
                'manifest' => [
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'display_name' => $manifest->displayName,
                    'provider_class' => $manifest->providerClass,
                    'api_version' => $manifest->apiVersion,
                    'hooks' => $manifest->hooks,
                    'permissions' => $manifest->permissions,
                    'settings_schema' => $manifest->settingsSchema,
                    'author' => $manifest->author,
                ],
            ]);

            $plugin->save();
            $upserted[] = $plugin;

            Log::debug('[PluginManager] Discovered plugin.', ['name' => $manifest->name]);
        }

        return $upserted;
    }

    // ── Install ────────────────────────────────────────────────────────────────

    /**
     * Install a discovered plugin: call its install() hook, run migrations, persist.
     *
     * @throws InvalidArgumentException When the plugin is not found.
     * @throws RuntimeException When installation fails.
     */
    public function install(string $name): Plugin
    {
        $plugin = $this->resolvePlugin($name);

        if ($plugin->isInstalled()) {
            throw new RuntimeException("Plugin [{$name}] is already installed.");
        }

        try {
            $provider = $this->resolveProvider($plugin);
            $provider->install();

            Artisan::call('migrate', ['--force' => true]);

            $plugin->status = 'installed';
            $plugin->installed_at = Carbon::now();
            $plugin->error_message = null;
            $plugin->save();

            Event::dispatch(new PluginInstalled($plugin));

            Log::info('[PluginManager] Plugin installed.', ['name' => $name]);
        } catch (Throwable $e) {
            $plugin->status = 'error';
            $plugin->error_message = $e->getMessage();
            $plugin->save();

            throw new RuntimeException("Plugin installation failed for [{$name}]: ".$e->getMessage(), 0, $e);
        }

        return $plugin;
    }

    // ── Activate ───────────────────────────────────────────────────────────────

    /**
     * Activate an installed (or inactive) plugin.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function activate(string $name): Plugin
    {
        $plugin = $this->resolvePlugin($name);

        if ($plugin->status === 'active') {
            throw new RuntimeException("Plugin [{$name}] is already active.");
        }

        if (! $plugin->isInstalled()) {
            throw new RuntimeException("Plugin [{$name}] must be installed before it can be activated.");
        }

        try {
            $provider = $this->resolveProvider($plugin);
            $provider->activate();

            $plugin->status = 'active';
            $plugin->activated_at = Carbon::now();
            $plugin->error_message = null;
            $plugin->save();

            Event::dispatch(new PluginActivated($plugin));

            Log::info('[PluginManager] Plugin activated.', ['name' => $name]);
        } catch (Throwable $e) {
            $plugin->status = 'error';
            $plugin->error_message = $e->getMessage();
            $plugin->save();

            throw new RuntimeException("Plugin activation failed for [{$name}]: ".$e->getMessage(), 0, $e);
        }

        return $plugin;
    }

    // ── Deactivate ─────────────────────────────────────────────────────────────

    /**
     * Deactivate an active plugin.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function deactivate(string $name): Plugin
    {
        $plugin = $this->resolvePlugin($name);

        if ($plugin->status !== 'active') {
            throw new RuntimeException("Plugin [{$name}] is not active.");
        }

        try {
            $provider = $this->resolveProvider($plugin);
            $provider->deactivate();

            $plugin->status = 'inactive';
            $plugin->error_message = null;
            $plugin->save();

            Event::dispatch(new PluginDeactivated($plugin));

            Log::info('[PluginManager] Plugin deactivated.', ['name' => $name]);
        } catch (Throwable $e) {
            $plugin->status = 'error';
            $plugin->error_message = $e->getMessage();
            $plugin->save();

            throw new RuntimeException("Plugin deactivation failed for [{$name}]: ".$e->getMessage(), 0, $e);
        }

        return $plugin;
    }

    // ── Uninstall ──────────────────────────────────────────────────────────────

    /**
     * Uninstall a plugin: call its uninstall() hook, roll back migrations, soft-delete.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function uninstall(string $name): Plugin
    {
        $plugin = $this->resolvePlugin($name);

        if (! $plugin->isInstalled()) {
            throw new RuntimeException("Plugin [{$name}] is not installed.");
        }

        try {
            $provider = $this->resolveProvider($plugin);
            $provider->uninstall();

            Artisan::call('migrate:rollback', ['--force' => true, '--step' => 1]);

            $plugin->status = 'discovered';
            $plugin->installed_at = null;
            $plugin->activated_at = null;
            $plugin->error_message = null;
            $plugin->save();

            Event::dispatch(new PluginUninstalled($plugin));

            $plugin->delete(); // soft-delete

            Log::info('[PluginManager] Plugin uninstalled.', ['name' => $name]);
        } catch (Throwable $e) {
            $plugin->status = 'error';
            $plugin->error_message = $e->getMessage();
            $plugin->save();

            throw new RuntimeException("Plugin uninstallation failed for [{$name}]: ".$e->getMessage(), 0, $e);
        }

        return $plugin;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * @throws InvalidArgumentException
     */
    private function resolvePlugin(string $name): Plugin
    {
        $plugin = Plugin::where('name', $name)->first();

        if ($plugin === null) {
            throw new InvalidArgumentException("Plugin [{$name}] not found. Run discover() first.");
        }

        return $plugin;
    }

    /**
     * Resolve the PluginServiceProvider for a Plugin model.
     *
     * @throws RuntimeException
     */
    private function resolveProvider(Plugin $plugin): PluginServiceProvider
    {
        /** @var array<string, mixed> $manifest */
        $manifest = $plugin->manifest;
        $class = (string) ($manifest['provider_class'] ?? '');

        if ($class === '' || ! class_exists($class)) {
            throw new RuntimeException(
                "Provider class [{$class}] for plugin [{$plugin->name}] not found."
            );
        }

        if (! is_a($class, PluginServiceProvider::class, true)) {
            throw new RuntimeException(
                "Provider [{$class}] must extend ".PluginServiceProvider::class.'.'
            );
        }

        /** @var PluginServiceProvider */
        return app($class);
    }
}
