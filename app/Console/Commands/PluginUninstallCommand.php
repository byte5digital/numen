<?php

namespace App\Console\Commands;

use App\Plugin\PluginManager;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PluginUninstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'numen:plugin:uninstall {name : The plugin name to uninstall}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uninstall a plugin and remove its data';

    /**
     * Execute the console command.
     */
    public function handle(PluginManager $manager): int
    {
        $name = (string) $this->argument('name');

        if (! $this->confirm("Are you sure you want to uninstall plugin [{$name}]? This will remove its data.")) {
            $this->info('Uninstall cancelled.');

            return self::SUCCESS;
        }

        $this->info("Uninstalling plugin: {$name}");

        try {
            $plugin = $manager->uninstall($name);
            $this->info("✅ Plugin [{$plugin->name}] uninstalled successfully.");

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error("Plugin not found: {$e->getMessage()}");

            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->error("Uninstall failed: {$e->getMessage()}");

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Unexpected error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
