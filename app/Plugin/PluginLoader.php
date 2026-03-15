<?php

namespace App\Plugin;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Discovers, validates, and boots Numen plugin providers.
 *
 * Discovery order:
 *   1. Composer installed.json (packages that declare "type": "numen-plugin")
 *   2. Local directories listed in config('plugins.plugin_paths')
 *
 * For each discovered manifest the loader:
 *   - Validates the API version constraint
 *   - Checks the `plugins` DB table for active status (if table exists)
 *   - Registers and boots the provider class into the application
 */
class PluginLoader
{
    /** @var array<PluginManifest> */
    private array $loaded = [];

    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * Discover all valid, active plugins and boot their providers.
     */
    public function boot(): void
    {
        $apiVersion = (string) config('plugins.plugin_api_version', '1.0');
        $maxPlugins = (int) config('plugins.max_plugins', 50);

        $manifests = $this->discover();

        $booted = 0;
        foreach ($manifests as $manifest) {
            if ($booted >= $maxPlugins) {
                Log::warning('[PluginLoader] Max plugin limit reached, skipping remaining plugins.', [
                    'max' => $maxPlugins,
                ]);
                break;
            }

            if (! $manifest->satisfiesApiVersion($apiVersion)) {
                Log::warning('[PluginLoader] Plugin API version mismatch, skipping.', [
                    'plugin' => $manifest->name,
                    'plugin_api_version' => $manifest->apiVersion,
                    'required_api_version' => $apiVersion,
                ]);

                continue;
            }

            if (! $this->isActive($manifest)) {
                continue;
            }

            try {
                $this->bootProvider($manifest);
                $this->loaded[] = $manifest;
                $booted++;
            } catch (Throwable $e) {
                Log::error('[PluginLoader] Failed to boot plugin.', [
                    'plugin' => $manifest->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Return all successfully booted plugin manifests.
     *
     * @return array<PluginManifest>
     */
    public function getLoaded(): array
    {
        return $this->loaded;
    }

    // ── Discovery ──────────────────────────────────────────────────────────────

    /**
     * Collect all discoverable PluginManifest instances.
     *
     * @return array<PluginManifest>
     */
    private function discover(): array
    {
        return array_merge(
            $this->discoverFromComposer(),
            $this->discoverFromPluginPaths(),
        );
    }

    /**
     * Parse vendor/composer/installed.json for packages of type "numen-plugin".
     *
     * @return array<PluginManifest>
     */
    private function discoverFromComposer(): array
    {
        $installedJson = base_path('vendor/composer/installed.json');
        if (! file_exists($installedJson)) {
            return [];
        }

        $raw = file_get_contents($installedJson);
        if ($raw === false) {
            return [];
        }

        /** @var array{packages?: array<array{name: string, type: string, extra?: array<string, mixed>}>}|null $installed */
        $installed = json_decode($raw, true);

        if (! is_array($installed)) {
            return [];
        }

        $packages = $installed['packages'] ?? (array_values($installed) ?: []);

        $manifests = [];
        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }

            if (($package['type'] ?? '') !== 'numen-plugin') {
                continue;
            }

            $packageName = $package['name'] ?? '';
            if ($packageName === '') {
                continue;
            }

            // Check for numen-plugin.json in the vendor directory
            $pluginJsonPath = base_path('vendor/'.$packageName.'/numen-plugin.json');
            if (file_exists($pluginJsonPath)) {
                try {
                    $manifests[] = PluginManifest::fromFile($pluginJsonPath);
                } catch (InvalidArgumentException $e) {
                    Log::warning('[PluginLoader] Invalid Composer plugin manifest.', [
                        'package' => $packageName,
                        'error' => $e->getMessage(),
                    ]);
                }

                continue;
            }

            // Fall back to extras.numen-plugin key in composer.json
            /** @var array<string, mixed>|null $extras */
            $extras = is_array($package['extra'] ?? null) ? $package['extra']['numen-plugin'] ?? null : null;
            if (is_array($extras)) {
                $extras['name'] ??= $packageName;
                $extras['version'] ??= $package['version'] ?? '0.0.0';
                try {
                    $manifests[] = PluginManifest::fromArray($extras);
                } catch (InvalidArgumentException $e) {
                    Log::warning('[PluginLoader] Invalid Composer plugin extras.', [
                        'package' => $packageName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $manifests;
    }

    /**
     * Scan directories listed in config('plugins.plugin_paths') for
     * numen-plugin.json files.
     *
     * @return array<PluginManifest>
     */
    private function discoverFromPluginPaths(): array
    {
        /** @var array<string>|mixed $pluginPaths */
        $pluginPaths = config('plugins.plugin_paths', []);
        if (! is_array($pluginPaths)) {
            return [];
        }

        $manifests = [];
        foreach ($pluginPaths as $basePath) {
            $basePath = (string) $basePath;
            if (! is_dir($basePath)) {
                continue;
            }

            // Each sub-directory is a potential plugin
            $entries = scandir($basePath);
            if ($entries === false) {
                continue;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $pluginDir = $basePath.DIRECTORY_SEPARATOR.$entry;
                if (! is_dir($pluginDir)) {
                    continue;
                }

                $manifestPath = $pluginDir.DIRECTORY_SEPARATOR.'numen-plugin.json';
                if (! file_exists($manifestPath)) {
                    continue;
                }

                try {
                    $manifests[] = PluginManifest::fromFile($manifestPath);
                } catch (InvalidArgumentException $e) {
                    Log::warning('[PluginLoader] Invalid local plugin manifest.', [
                        'path' => $manifestPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $manifests;
    }

    // ── Active check ───────────────────────────────────────────────────────────

    /**
     * Return true when the plugin is considered active.
     *
     * If the `plugins` table exists, the plugin must have a row with
     * status = 'active'.  When the table does not yet exist (e.g. fresh
     * install before migrations), all discovered plugins are considered active
     * so the application can still boot.
     */
    private function isActive(PluginManifest $manifest): bool
    {
        if (! Schema::hasTable('plugins')) {
            // Migrations haven't run yet — treat all plugins as active
            return true;
        }

        /** @var object{status: string}|null $row */
        $row = DB::table('plugins')
            ->where('name', $manifest->name)
            ->first(['status']);

        return $row !== null && $row->status === 'active';
    }

    // ── Provider booting ───────────────────────────────────────────────────────

    /**
     * Instantiate and register the plugin's service provider.
     *
     * @throws InvalidArgumentException
     */
    private function bootProvider(PluginManifest $manifest): void
    {
        $class = $manifest->providerClass;

        if (! class_exists($class)) {
            throw new InvalidArgumentException(
                "Plugin provider class [{$class}] for plugin [{$manifest->name}] does not exist."
            );
        }

        if (! is_a($class, PluginServiceProvider::class, true)) {
            throw new InvalidArgumentException(
                "Plugin provider [{$class}] must extend ".PluginServiceProvider::class.'.'
            );
        }

        /** @var PluginServiceProvider $provider */
        $provider = new $class($this->app);
        $provider->setManifest($manifest);

        $this->app->register($provider);
    }
}
