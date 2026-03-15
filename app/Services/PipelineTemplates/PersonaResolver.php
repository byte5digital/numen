<?php

namespace App\Services\PipelineTemplates;

use App\Models\Persona;
use App\Models\Space;

class PersonaResolver
{
    /**
     * @param  array<int, array<string, mixed>>  $personaDefinitions
     * @return array<string, Persona>
     */
    public function resolvePersonas(array $personaDefinitions, Space $space): array
    {
        $resolved = [];
        foreach ($personaDefinitions as $def) {
            $ref = $def['persona_ref'] ?? ($def['name'] ?? '');
            $name = $def['name'] ?? $ref;
            if (empty($ref)) {
                continue;
            }
            $persona = $this->findExistingPersona($space, $name)
                ?? $this->createPersona($space, $def, $name);
            $resolved[$ref] = $persona;
        }

        return $resolved;
    }

    private function findExistingPersona(Space $space, string $name): ?Persona
    {
        /** @var Persona|null */
        return $space->personas()->where('name', $name)->first();
    }

    /** @param array<string, mixed> $def */
    private function createPersona(Space $space, array $def, string $name): Persona
    {
        return Persona::create([
            'space_id' => $space->id,
            'name' => $name,
            'role' => $def['role'] ?? 'creator',
            'system_prompt' => $def['system_prompt'] ?? '',
            'capabilities' => $def['capabilities'] ?? ['content_generation'],
            'model_config' => $def['model_config'] ?? [
                'model' => config('numen.models.generation', 'claude-sonnet-4-6'),
                'temperature' => 0.7,
                'max_tokens' => 4096,
            ],
            'voice_guidelines' => $def['voice_guidelines'] ?? null,
            'constraints' => $def['constraints'] ?? null,
            'is_active' => true,
        ]);
    }
}
