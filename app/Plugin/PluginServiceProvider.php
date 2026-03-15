<?php

namespace App\Plugin;

use Illuminate\Support\ServiceProvider;

/**
 * Abstract base class for all Numen plugin service providers.
 *
 * Plugin authors extend this class and implement registerHooks() at minimum.
 * The lifecycle methods (install, activate, deactivate, uninstall) are called
 * by PluginLoader at the appropriate times.
 */
abstract class PluginServiceProvider extends ServiceProvider
{
    /**
     * The plugin manifest, set by PluginLoader before booting.
     */
    protected PluginManifest $manifest;

    /**
     * Register the plugin's hooks with the central HookRegistry.
     *
     * This is the primary extension point. Called during application boot
     * after all service providers have been registered.
     */
    abstract public function registerHooks(HookRegistry $registry): void;

    /**
     * Called once when the plugin is first installed (before activation).
     * Run migrations, seed default data, etc.
     */
    public function install(): void
    {
        // No-op by default — override in your plugin
    }

    /**
     * Called when the plugin is activated by an admin.
     * Enable scheduled tasks, register listeners, etc.
     */
    public function activate(): void
    {
        // No-op by default — override in your plugin
    }

    /**
     * Called when the plugin is deactivated by an admin.
     * Disable scheduled tasks, clean up transient state, etc.
     */
    public function deactivate(): void
    {
        // No-op by default — override in your plugin
    }

    /**
     * Called when the plugin is uninstalled.
     * Drop tables, delete stored files, remove settings, etc.
     */
    public function uninstall(): void
    {
        // No-op by default — override in your plugin
    }

    /**
     * Bootstrap any additional application services specific to this plugin.
     * Called by Laravel's service provider pipeline.
     */
    public function boot(): void
    {
        /** @var HookRegistry $registry */
        $registry = $this->app->make(HookRegistry::class);
        $this->registerHooks($registry);
    }

    /**
     * Set the manifest on this provider (called by PluginLoader).
     *
     * @internal
     */
    public function setManifest(PluginManifest $manifest): void
    {
        $this->manifest = $manifest;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Read a plugin setting value from the application config.
     *
     * Settings are stored under plugins.<plugin-name>.<key> in the config.
     */
    protected function setting(string $key, mixed $default = null): mixed
    {
        $configKey = 'plugins.plugin_settings.'.$this->manifest->name.'.'.$key;

        return config($configKey, $default);
    }

    /**
     * Get the absolute filesystem path to this plugin's root directory.
     *
     * If the plugin was loaded from a plugin_paths directory, returns that path.
     * Composer-installed plugins use vendor/<package-name>.
     */
    protected function pluginPath(string $relative = ''): string
    {
        // Derive the path from Composer's vendor directory for the plugin package
        // (plugin name format: vendor/package → vendor/vendor/package)
        $vendorPath = base_path('vendor/'.str_replace('/', DIRECTORY_SEPARATOR, $this->manifest->name));

        // Fall back to checking configured plugin_paths
        if (! is_dir($vendorPath)) {
            $pluginPaths = config('plugins.plugin_paths', []);
            if (is_array($pluginPaths)) {
                foreach ($pluginPaths as $basePath) {
                    $candidate = rtrim((string) $basePath, '/').'/'.$this->manifest->name;
                    if (is_dir($candidate)) {
                        $vendorPath = $candidate;
                        break;
                    }
                }
            }
        }

        if ($relative === '') {
            return $vendorPath;
        }

        return $vendorPath.DIRECTORY_SEPARATOR.ltrim($relative, '/\\');
    }
}
