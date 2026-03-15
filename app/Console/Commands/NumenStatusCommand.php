<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Models\ContentBrief;
use App\Models\PipelineRun;
use App\Models\Space;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class NumenStatusCommand extends Command
{
    protected $signature = 'numen:status
        {--details : Show extended provider configuration}';

    protected $description = 'Display Numen system health (DB, queue, AI providers, content stats)';

    public function handle(): int
    {
        $this->info('Numen System Status');
        $this->line(str_repeat('─', 50));

        $allGood = true;

        // --- Database ---
        $this->newLine();
        $this->line('<fg=cyan>Database</>');

        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $this->line("  <fg=green>✓</> Connected ({$driver})");
        } catch (\Throwable $e) {
            $this->line('  <fg=red>✗</> Database connection failed: '.$e->getMessage());
            $allGood = false;
        }

        // Content stats
        try {
            $spaces = Space::count();
            $contents = Content::count();
            $briefs = ContentBrief::count();
            $runs = PipelineRun::count();
            $runningPipelines = PipelineRun::where('status', 'running')->count();

            $this->line("  Spaces: {$spaces} | Content: {$contents} | Briefs: {$briefs} | Runs: {$runs}");

            if ($runningPipelines > 0) {
                $this->line("  <fg=yellow>⚡</> {$runningPipelines} pipeline(s) currently running");
            }
        } catch (\Throwable $e) {
            $this->line('  <fg=yellow>!</> Could not fetch counts: '.$e->getMessage());
        }

        // --- Cache ---
        $this->newLine();
        $this->line('<fg=cyan>Cache</>');

        try {
            $testKey = 'numen:status:ping:'.time();
            Cache::put($testKey, true, 5);
            $hit = Cache::get($testKey);
            Cache::forget($testKey);
            $driver = config('cache.default', 'unknown');

            if ($hit) {
                $this->line("  <fg=green>✓</> Cache working ({$driver})");
            } else {
                $this->line("  <fg=yellow>!</> Cache write/read mismatch ({$driver})");
                $allGood = false;
            }
        } catch (\Throwable $e) {
            $this->line('  <fg=red>✗</> Cache error: '.$e->getMessage());
            $allGood = false;
        }

        // --- Queue ---
        $this->newLine();
        $this->line('<fg=cyan>Queue</>');

        try {
            $queueDriver = config('queue.default', 'unknown');
            $this->line("  Driver: {$queueDriver}");

            if (in_array($queueDriver, ['sync', 'null'])) {
                $this->line('  <fg=yellow>!</> Queue is synchronous — jobs run inline (not recommended for production)');
            } else {
                $this->line('  <fg=green>✓</> Async queue configured');
            }
        } catch (\Throwable $e) {
            $this->line('  <fg=red>✗</> Queue error: '.$e->getMessage());
            $allGood = false;
        }

        // --- AI Providers ---
        $this->newLine();
        $this->line('<fg=cyan>AI Providers</>');

        $providers = ['anthropic', 'openai', 'azure'];
        $defaultProvider = config('numen.default_provider', 'anthropic');

        foreach ($providers as $provider) {
            $configured = $this->isProviderConfigured($provider);
            $isDefault = $provider === $defaultProvider ? ' (default)' : '';
            $icon = $configured ? '<fg=green>✓</>' : '<fg=gray>–</>';
            $label = $configured ? 'configured' : 'not configured';
            $this->line("  {$icon} {$provider}{$isDefault}: {$label}");

            if ($this->option('details') && $configured) {
                $model = config("numen.providers.{$provider}.default_model", '?');
                $this->line("      Model: {$model}");
            }
        }

        // Image providers
        $imageProvider = config('numen.image_generation.default_provider', 'none');
        $this->line("  Image generation: {$imageProvider}");

        // --- Summary ---
        $this->newLine();
        $this->line(str_repeat('─', 50));

        if ($allGood) {
            $this->info('All systems operational.');
        } else {
            $this->warn('Some checks failed — see above for details.');
        }

        return $allGood ? self::SUCCESS : self::FAILURE;
    }

    private function isProviderConfigured(string $provider): bool
    {
        return match ($provider) {
            'anthropic' => ! empty(config('numen.providers.anthropic.api_key')),
            'openai' => ! empty(config('numen.providers.openai.api_key')),
            'azure' => ! empty(config('numen.providers.azure.api_key')) && ! empty(config('numen.providers.azure.endpoint')),
            default => false,
        };
    }
}
