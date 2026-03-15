<?php

namespace App\Console\Commands;

use App\Models\Plugin;
use Illuminate\Console\Command;

class PluginListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'numen:plugin:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all discovered and installed plugins';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $plugins = Plugin::withTrashed()
            ->orderBy('name')
            ->get(['name', 'display_name', 'version', 'status', 'manifest']);

        if ($plugins->isEmpty()) {
            $this->info('No plugins found. Run numen:plugin:discover to scan for plugins.');

            return self::SUCCESS;
        }

        $rows = $plugins->map(function (Plugin $plugin): array {
            /** @var array<string, mixed> $manifest */
            $manifest = $plugin->manifest ?? [];
            /** @var array<string, mixed> $hooks */
            $hooks = is_array($manifest['hooks'] ?? null) ? $manifest['hooks'] : [];
            $hookNames = implode(', ', array_keys($hooks));

            return [
                $plugin->name,
                $plugin->version ?? 'n/a',
                $plugin->status ?? 'unknown',
                $hookNames ?: '—',
            ];
        })->toArray();

        $this->table(
            ['Name', 'Version', 'Status', 'Hooks'],
            $rows,
        );

        return self::SUCCESS;
    }
}
