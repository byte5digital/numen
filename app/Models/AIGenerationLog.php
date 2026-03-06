<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'messages'  => 'array',
        'metadata'  => 'array',
        'cost_usd'  => 'decimal:6',
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
