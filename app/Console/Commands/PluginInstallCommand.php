<?php

namespace App\Console\Commands;

use App\Plugin\PluginManager;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PluginInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'numen:plugin:install {name : The plugin name to install}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install a discovered plugin';

    /**
     * Execute the console command.
     */
    public function handle(PluginManager $manager): int
    {
        $name = (string) $this->argument('name');

        $this->info("Installing plugin: {$name}");

        try {
            $plugin = $manager->install($name);
            $this->info("✅ Plugin [{$plugin->name}] v{$plugin->version} installed successfully.");

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error("Plugin not found: {$e->getMessage()}");

            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->error("Installation failed: {$e->getMessage()}");

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Unexpected error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
