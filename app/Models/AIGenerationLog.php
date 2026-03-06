<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $pipeline_run_id
 * @property string|null $persona_id
 * @property string $model
 * @property string $purpose
 * @property array $messages
 * @property string $response
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_tokens
 * @property string $cost_usd
 * @property int $latency_ms
 * @property string|null $stop_reason
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * Virtual/aggregate properties (from selectRaw queries):
 * @property mixed $cost
 * @property mixed $date
 * @property mixed $calls
 * @property mixed $total_cost
 * @property mixed $total_calls
 * @property mixed $total_input
 * @property mixed $total_output
 * @property mixed $count
 * @property-read PipelineRun|null $pipelineRun
 * @property-read Persona|null $persona
 */
class AIGenerationLog extends Model
{
    use HasUlids;

    protected $table = 'ai_generation_logs';

    protected $fillable = [
        'pipeline_run_id', 'persona_id', 'model', 'purpose',
        'messages', 'response',
        'input_tokens', 'output_tokens', 'cache_read_tokens',
        'cost_usd', 'latency_ms', 'stop_reason', 'metadata',
    ];

    protected $casts = [
        'messages' => 'array',
        'metadata' => 'array',
        'cost_usd' => 'decimal:6',
    ];

    public function pipelineRun(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
