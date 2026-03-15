<?php

namespace App\Console\Commands;

use App\Plugin\PluginManager;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PluginDeactivateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'numen:plugin:deactivate {name : The plugin name to deactivate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate an active plugin';

    /**
     * Execute the console command.
     */
    public function handle(PluginManager $manager): int
    {
        $name = (string) $this->argument('name');

        $this->info("Deactivating plugin: {$name}");

        try {
            $plugin = $manager->deactivate($name);
            $this->info("✅ Plugin [{$plugin->name}] deactivated successfully.");

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error("Plugin not found: {$e->getMessage()}");

            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->error("Deactivation failed: {$e->getMessage()}");

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Unexpected error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
