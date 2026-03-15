<?php

namespace App\Console\Commands;

use App\Plugin\PluginManager;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PluginActivateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'numen:plugin:activate {name : The plugin name to activate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activate an installed plugin';

    /**
     * Execute the console command.
     */
    public function handle(PluginManager $manager): int
    {
        $name = (string) $this->argument('name');

        $this->info("Activating plugin: {$name}");

        try {
            $plugin = $manager->activate($name);
            $this->info("✅ Plugin [{$plugin->name}] activated successfully.");

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error("Plugin not found: {$e->getMessage()}");

            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->error("Activation failed: {$e->getMessage()}");

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Unexpected error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
