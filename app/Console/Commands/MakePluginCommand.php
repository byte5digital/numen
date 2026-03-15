<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePluginCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'numen:make-plugin {name : The plugin name (e.g. my-awesome-plugin)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scaffold a new plugin skeleton in plugins/{name}/';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rawName = (string) $this->argument('name');

        // Normalise: lowercase-kebab-case for directory/manifest name
        $pluginSlug = Str::slug($rawName);
        if ($pluginSlug === '') {
            $this->error("Invalid plugin name: [{$rawName}]");

            return self::FAILURE;
        }

        // PascalCase class name
        $className = Str::studly($rawName);

        $pluginDir = base_path("plugins/{$pluginSlug}");

        if (is_dir($pluginDir)) {
            $this->error("Plugin directory already exists: plugins/{$pluginSlug}");

            return self::FAILURE;
        }

        $this->info("Scaffolding plugin [{$pluginSlug}] in plugins/{$pluginSlug}/...");

        // Create directory structure
        $dirs = [
            $pluginDir.'/src',
            $pluginDir.'/database/migrations',
            $pluginDir.'/resources/js',
            $pluginDir.'/tests',
        ];

        foreach ($dirs as $dir) {
            if (! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                $this->error("Failed to create directory: {$dir}");

                return self::FAILURE;
            }
        }

        // Write files
        $files = [
            $pluginDir.'/numen-plugin.json' => $this->manifestStub($pluginSlug, $className),
            $pluginDir."/src/{$className}ServiceProvider.php" => $this->providerStub($pluginSlug, $className),
            $pluginDir.'/src/readme.md' => $this->readmeStub($pluginSlug),
            $pluginDir.'/database/migrations/.gitkeep' => '',
            $pluginDir.'/resources/js/.gitkeep' => '',
            $pluginDir.'/tests/.gitkeep' => '',
        ];

        foreach ($files as $path => $contents) {
            if (file_put_contents($path, $contents) === false) {
                $this->error("Failed to write file: {$path}");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info("✅ Plugin scaffold created at plugins/{$pluginSlug}/");
        $this->newLine();
        $this->line('Next steps:');
        $this->line("  1. Edit  plugins/{$pluginSlug}/numen-plugin.json  — fill in metadata");
        $this->line("  2. Edit  plugins/{$pluginSlug}/src/{$className}ServiceProvider.php  — add hooks");
        $this->line("  3. Add   plugins/{$pluginSlug}  to config/plugins.php plugin_paths");
        $this->line('  4. Run   php artisan numen:plugin:discover');

        return self::SUCCESS;
    }

    private function manifestStub(string $slug, string $className): string
    {
        $namespace = "Plugin\\{$className}";
        $providerClass = "{$namespace}\\{$className}ServiceProvider";

        return json_encode([
            'name' => $slug,
            'version' => '0.1.0',
            'display_name' => $className,
            'description' => 'A Numen plugin.',
            'author' => '',
            'api_version' => '1.0',
            'provider_class' => $providerClass,
            'hooks' => [],
            'permissions' => [],
            'settings_schema' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    private function providerStub(string $slug, string $className): string
    {
        return <<<PHP
<?php

namespace Plugin\\{$className};

use App\\Plugin\\PluginServiceProvider;

class {$className}ServiceProvider extends PluginServiceProvider
{
    /**
     * Register plugin services.
     */
    public function register(): void
    {
        parent::register();
    }

    /**
     * Bootstrap plugin hooks, routes, and views.
     */
    public function boot(): void
    {
        parent::boot();

        // Register hooks, load routes, views, etc.
        // Example:
        // \$this->registerHook('content.render', function (array \$payload): array {
        //     return \$payload;
        // });
    }
}

PHP;
    }

    private function readmeStub(string $slug): string
    {
        return <<<MD
# {$slug}

A Numen plugin.

## Installation

1. Place this directory under `plugins/{$slug}/`
2. Add `plugins/{$slug}` to `config/plugins.php` `plugin_paths`
3. Run `php artisan numen:plugin:discover`
4. Run `php artisan numen:plugin:install {$slug}`
5. Run `php artisan numen:plugin:activate {$slug}`

## Hooks

_Document your hooks here._

## Configuration

_Document plugin settings here._
MD;
    }
}
