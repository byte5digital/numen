<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plugin System Configuration
    |--------------------------------------------------------------------------
    |
    | Numen supports a first-class plugin system. Plugins can be distributed
    | via Composer (type: "numen-plugin") or as local directories.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Plugin Paths
    |--------------------------------------------------------------------------
    |
    | Additional directories where Numen should look for local plugins.
    | Each directory should contain subdirectories, each with a numen-plugin.json
    | manifest at its root.
    |
    | Example: base_path('plugins') → plugins/my-plugin/numen-plugin.json
    |
    */
    'plugin_paths' => array_filter(
        array_map(
            'trim',
            explode(',', (string) env('NUMEN_PLUGIN_PATHS', '')),
        ),
        fn (string $p) => $p !== '',
    ),

    /*
    |--------------------------------------------------------------------------
    | Plugin API Version
    |--------------------------------------------------------------------------
    |
    | The API version that this Numen installation supports. Plugins declare
    | an `api_version` in their manifest; if it doesn't satisfy the constraint
    | defined here, the plugin will not be loaded.
    |
    | Format: "major.minor"  — plugin major must match, plugin minor >= required minor.
    |
    */
    'plugin_api_version' => env('NUMEN_PLUGIN_API_VERSION', '1.0'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Active Plugins
    |--------------------------------------------------------------------------
    |
    | Hard limit on the number of plugins that can be active simultaneously.
    | This protects against runaway resource usage on shared hosting environments.
    |
    */
    'max_plugins' => (int) env('NUMEN_MAX_PLUGINS', 50),

    /*
    |--------------------------------------------------------------------------
    | Plugin Settings Store
    |--------------------------------------------------------------------------
    |
    | Plugin-specific settings are namespaced under this key.
    | Each plugin's settings are stored as plugins.plugin_settings.<name>.*
    | This section is populated at runtime from the DB settings table.
    |
    */
    'plugin_settings' => [],

];
