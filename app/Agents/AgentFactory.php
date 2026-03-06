<?php

namespace App\Agents;

use App\Agents\Types\ContentCreatorAgent;
use App\Agents\Types\EditorialDirectorAgent;
use App\Agents\Types\SeoExpertAgent;
use App\Models\Persona;
use App\Services\AI\LLMManager;
use InvalidArgumentException;

class AgentFactory
{
    public function __construct(
        private LLMManager $client,
    ) {}

    /**
     * Create an agent instance from a persona.
     */
    public function make(Persona $persona): Agent
    {
        return match ($persona->role) {
            'creator' => new ContentCreatorAgent($persona, $this->client),
            'optimizer' => new SeoExpertAgent($persona, $this->client),
            'reviewer' => new EditorialDirectorAgent($persona, $this->client),
            default => throw new InvalidArgumentException("Unknown persona role: {$persona->role}"),
        };
    }

    /**
     * Create an agent from a persona found by role within a space.
     */
    public function makeByRole(string $spaceId, string $role): Agent
    {
        $persona = Persona::where('space_id', $spaceId)
            ->where('role', $role)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->make($persona);
    }
}
