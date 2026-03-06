<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Persona extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'space_id', 'name', 'role', 'system_prompt',
        'capabilities', 'model_config', 'voice_guidelines',
        'constraints', 'is_active',
    ];

    /**
     * Sensitive fields hidden from API/JSON serialization.
     * system_prompt may contain confidential business logic.
     */
    protected $hidden = [
        'system_prompt',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'model_config' => 'array',
        'voice_guidelines' => 'array',
        'constraints' => 'array',
        'is_active' => 'boolean',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function getModel(): string
    {
        return $this->model_config['model']
            ?? config('numen.models.generation', 'claude-sonnet-4-6');
    }

    public function getProvider(): ?string
    {
        return $this->model_config['provider'] ?? null;
    }

    /**
     * Returns "provider:model" if explicit provider set, otherwise just "model".
     * Passed directly to LLMManager which handles provider resolution.
     */
    public function getFullModel(): string
    {
        $provider = $this->getProvider();
        $model = $this->getModel();

        return $provider ? "{$provider}:{$model}" : $model;
    }

    /**
     * Returns "provider:model" fallback string if configured, null otherwise.
     * Used by LLMManager to override the generic cross-provider equivalents map.
     */
    public function getFallbackFullModel(): ?string
    {
        $model = $this->model_config['fallback_model'] ?? null;
        if (! $model) {
            return null;
        }
        $provider = $this->model_config['fallback_provider'] ?? null;

        return $provider ? "{$provider}:{$model}" : $model;
    }

    public function getTemperature(): float
    {
        return $this->model_config['temperature'] ?? 0.7;
    }

    public function getMaxTokens(): int
    {
        return $this->model_config['max_tokens'] ?? 4096;
    }
}
