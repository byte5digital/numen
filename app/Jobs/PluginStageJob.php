<?php

namespace App\Jobs;

use App\Models\PipelineRun;
use App\Pipelines\PipelineExecutor;
use App\Plugin\Contracts\PipelineStageContract;
use App\Plugin\HookRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued job that runs a plugin-registered pipeline stage.
 *
 * Resolves the handler class from HookRegistry and delegates to
 * PipelineStageContract::handle(), then advances the pipeline via
 * PipelineExecutor::advance().
 */
class PluginStageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 240;

    public int $maxExceptions = 2;

    public function __construct(
        public readonly PipelineRun $run,
        public readonly array $stage,
    ) {
        $this->onQueue(config('numen.queues.plugins', 'default'));
    }

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(HookRegistry $registry, PipelineExecutor $executor): void
    {
        $stageType = $this->stage['type'];

        Log::info('Running plugin pipeline stage', [
            'run_id' => $this->run->id,
            'stage' => $this->stage['name'] ?? $stageType,
            'type' => $stageType,
        ]);

        $handlerClass = $registry->getPipelineStageHandler($stageType);

        if ($handlerClass === null) {
            $message = "No handler registered for plugin stage type [{$stageType}].";
            Log::error($message, ['run_id' => $this->run->id]);
            $this->run->markFailed($message);

            return;
        }

        try {
            /** @var PipelineStageContract $handler */
            $handler = app($handlerClass);

            $stageConfig = $this->stage['config'] ?? [];
            $result = $handler->handle($this->run, $stageConfig);

            $result = array_merge(['success' => true, 'stage' => $this->stage['name'] ?? $stageType], $result);

            $executor->advance($this->run, $result);

        } catch (\Throwable $e) {
            Log::error('Plugin stage exception', [
                'run_id' => $this->run->id,
                'stage' => $this->stage['name'] ?? $stageType,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->run->markFailed(
                    "Plugin stage [{$stageType}] failed after {$this->tries} attempts: {$e->getMessage()}"
                );
            }

            throw $e;
        }
    }
}
