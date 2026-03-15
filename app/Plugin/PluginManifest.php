<?php

namespace App\Plugin;

use InvalidArgumentException;

/**
 * Value object representing a parsed numen-plugin.json manifest.
 */
final class PluginManifest
{
    /** @param array<string, mixed> $hooks */
    /** @param array<string> $permissions */
    /** @param array<string, mixed> $settingsSchema */
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly string $displayName,
        public readonly string $providerClass,
        public readonly string $apiVersion,
        public readonly array $hooks = [],
        public readonly array $permissions = [],
        public readonly array $settingsSchema = [],
        public readonly string $description = '',
        public readonly string $author = '',
    ) {}

    /**
     * Parse a numen-plugin.json file and return a PluginManifest.
     *
     * @throws InvalidArgumentException
     */
    public static function fromFile(string $path): self
    {
        if (! file_exists($path)) {
            throw new InvalidArgumentException("Plugin manifest not found: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new InvalidArgumentException("Cannot read plugin manifest: {$path}");
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new InvalidArgumentException("Invalid JSON in plugin manifest: {$path}");
        }

        return self::fromArray($data, dirname($path));
    }

    /**
     * Parse from an array (e.g. from Composer installed.json extras).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data, string $basePath = ''): self
    {
        foreach (['name', 'version', 'display_name', 'provider', 'api_version'] as $required) {
            if (empty($data[$required])) {
                throw new InvalidArgumentException("Plugin manifest missing required field: {$required}");
            }
        }

        /** @var string $name */
        $name = $data['name'];
        /** @var string $version */
        $version = $data['version'];
        /** @var string $displayName */
        $displayName = $data['display_name'];
        /** @var string $providerClass */
        $providerClass = $data['provider'];
        /** @var string $apiVersion */
        $apiVersion = $data['api_version'];

        return new self(
            name: $name,
            version: $version,
            displayName: $displayName,
            providerClass: $providerClass,
            apiVersion: $apiVersion,
            hooks: is_array($data['hooks'] ?? null) ? $data['hooks'] : [],
            permissions: is_array($data['permissions'] ?? null) ? $data['permissions'] : [],
            settingsSchema: is_array($data['settings_schema'] ?? null) ? $data['settings_schema'] : [],
            description: is_string($data['description'] ?? null) ? $data['description'] : '',
            author: is_string($data['author'] ?? null) ? $data['author'] : '',
        );
    }

    /**
     * Check whether this plugin's API version satisfies a constraint.
     *
     * Supports simple ^major.minor constraints: the major must match and
     * the plugin's minor must be >= the required minor.
     */
    public function satisfiesApiVersion(string $requiredApiVersion): bool
    {
        // Strip leading ^ if present
        $required = ltrim($requiredApiVersion, '^~');
        $pluginVer = ltrim($this->apiVersion, '^~');

        [$reqMajor, $reqMinor] = array_map('intval', explode('.', $required.'.0'));
        [$plugMajor, $plugMinor] = array_map('intval', explode('.', $pluginVer.'.0'));

        return $plugMajor === $reqMajor && $plugMinor >= $reqMinor;
    }
}
