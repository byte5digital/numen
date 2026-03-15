# Numen Plugin Developer Guide

> Create powerful plugins that extend Numen's AI pipeline, UI, and content events.

---

## Overview

The Numen Plugin System lets third-party developers extend the platform without touching core code.
Plugins can:

- Add custom **pipeline stages** (e.g. a plagiarism-check stage)
- Register custom **LLM providers** or **image providers**
- React to **content lifecycle events** (created, updated, published)
- Add **admin menu items** and **Vue dashboard widgets**
- Expose **per-space settings** to admins via the API

---

## Quick Start

Generate a plugin skeleton:

```bash
php artisan make:plugin vendor/my-plugin
```

This creates:

```
plugins/
+-- vendor/
    +-- my-plugin/
        +-- numen-plugin.json
        +-- src/
        |   +-- MyPluginServiceProvider.php
        +-- resources/
            +-- views/
```

---

## Plugin Manifest (numen-plugin.json)

Every plugin must have a numen-plugin.json in its root directory.

```json
{
    "name": "vendor/my-plugin",
    "version": "1.0.0",
    "display_name": "My Plugin",
    "description": "What this plugin does.",
    "author": "Your Name",
    "provider": "Vendor\\MyPlugin\\MyPluginServiceProvider",
    "api_version": "1.0",
    "hooks": {
        "pipeline.stages": ["grammar_check"],
        "llm.providers": ["my-llm"],
        "content.events": ["content.created", "content.published"]
    },
    "permissions": ["my_plugin.manage"],
    "settings_schema": [
        {"key": "api_url",    "type": "text",     "label": "API URL",    "required": true},
        {"key": "api_secret", "type": "password", "label": "API Secret", "secret": true}
    ]
}
```

### Required fields

- name         : Unique slug: vendor/package-name
- version      : SemVer string (1.0.0)
- display_name : Human-readable name shown in the admin UI
- provider     : Fully-qualified class name of your ServiceProvider
- api_version  : Numen Plugin API version this plugin targets (1.0)

---

## Service Provider

Your plugin's entry point must extend App\Plugin\PluginServiceProvider:

```php
<?php

namespace Vendor\MyPlugin;

use App\Plugin\HookRegistry;
use App\Plugin\PluginServiceProvider;

class MyPluginServiceProvider extends PluginServiceProvider
{
    public function boot(HookRegistry $hooks): void
    {
        $hooks->registerPipelineStage('grammar_check', function (array $payload): array {
            return $payload;
        });

        $hooks->onContentEvent('content.published', function ($content): void {
            // Notify your external system
        });
    }

    public function install(): void {}
    public function activate(): void {}
    public function deactivate(): void {}
    public function uninstall(): void {}
}
```

---

## Hook Reference

### Pipeline Stages

```php
$hooks->registerPipelineStage('my_stage', function (array $payload): array {
    return $payload;
});
```

### LLM Providers

```php
$hooks->registerLLMProvider('my-llm', function () {
    return new MyLLMProvider();
});
```

### Content Events

```php
$hooks->onContentEvent('content.created', function ($content): void { ... });
$hooks->onContentEvent('content.updated', function ($content): void { ... });
$hooks->onContentEvent('content.published', function ($content): void { ... });
```

### Vue Components

```php
$hooks->registerVueComponent('MyPluginWidget', '@plugins/vendor/my-plugin/Widget.vue');
```

---

## Settings

Settings are stored per-plugin (and optionally per-space) in the plugin_settings table.
Mark sensitive fields with "secret": true to have them masked as "***" in API responses.

---

## Artisan Commands

```bash
php artisan plugin:discover          # Scan plugins/ and register
php artisan plugin:install vendor/my-plugin
php artisan plugin:activate vendor/my-plugin
php artisan plugin:deactivate vendor/my-plugin
php artisan plugin:uninstall vendor/my-plugin
php artisan plugin:list
php artisan make:plugin vendor/my-plugin
```

---

## Admin API

All endpoints require auth:sanctum and plugins.manage permission.

- GET    /api/v1/admin/plugins
- GET    /api/v1/admin/plugins/{name}
- POST   /api/v1/admin/plugins/{name}/install
- POST   /api/v1/admin/plugins/{name}/activate
- POST   /api/v1/admin/plugins/{name}/deactivate
- POST   /api/v1/admin/plugins/{name}/uninstall
- PATCH  /api/v1/admin/plugins/{name}/settings

---

## Environment Variables

```
NUMEN_PLUGINS_ENABLED=true
NUMEN_PLUGINS_PATH=plugins
NUMEN_PLUGIN_API_VERSION=1.0
```

---

## API Version Compatibility

A plugin with api_version "1.0" is compatible with Numen instances supporting ^1.0 (same major, minor >= 0).

```php
$manifest->satisfiesApiVersion('^1.0'); // true for 1.x, false for 2.x
```

---

*Plugin System introduced in Numen v0.9.0*
