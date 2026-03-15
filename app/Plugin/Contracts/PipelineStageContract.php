<?php

namespace App\Plugin\Contracts;

use App\Models\PipelineRun;

/**
 * Contract for plugin-provided pipeline stage handlers.
 *
 * Implementations declare a unique stage type (e.g. "fact_check") and a
 * human-readable label.  The core pipeline dispatches PluginStageJob when it
 * encounters a stage type that is registered via HookRegistry::registerPipelineStage().
 */
interface PipelineStageContract
{
    /**
     * Machine identifier for this stage type (e.g. "fact_check").
     * Must be unique across all registered plugins.
     */
    public static function type(): string;

    /**
     * Human-readable label shown in the pipeline builder UI.
     */
    public static function label(): string;

    /**
     * JSON-Schema-compatible description of accepted stage configuration keys.
     *
     * Example:
     *   [
     *     'threshold' => ['type' => 'number', 'default' => 0.8],
     *     'sources'   => ['type' => 'array',  'items' => ['type' => 'string']],
     *   ]
     *
     * @return array<string, mixed>
     */
    public static function configSchema(): array;

    /**
     * Execute the stage logic.
     *
     * Receives the current PipelineRun (with full context) and the stage's
     * configuration array from the pipeline definition.
     *
     * Must return a result array that will be passed to PipelineExecutor::advance().
     * At minimum return ['success' => true].
     *
     * @param  array<string, mixed>  $stageConfig
     * @return array<string, mixed>
     */
    public function handle(PipelineRun $run, array $stageConfig): array;
}
