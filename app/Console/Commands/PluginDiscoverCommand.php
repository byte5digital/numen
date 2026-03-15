<?php

namespace App\Console\Commands;

use App\Models\Plugin;
use App\Plugin\PluginManager;
use Illuminate\Console\Command;
use Throwable;

class PluginDiscoverCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'numen:plugin:discover';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan for new plugins and register them in the database';

    /**
     * Execute the console command.
     */
    public function handle(PluginManager $manager): int
    {
        $this->info('Discovering plugins...');

        try {
            /** @var array<Plugin> $plugins */
            $plugins = $manager->discover();

            if (empty($plugins)) {
                $this->info('No plugins discovered. Check your plugins.plugin_paths configuration.');

                return self::SUCCESS;
            }

            $this->info(sprintf('Discovered %d plugin(s):', count($plugins)));

            foreach ($plugins as $plugin) {
                $this->line("  • [{$plugin->name}] v{$plugin->version} — {$plugin->status}");
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Discovery failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
