<?php

namespace App\Agents;

use App\Models\Persona;
use App\Services\AI\LLMManager;

abstract class Agent
{
    public function __construct(
        protected Persona $persona,
        protected LLMManager $client,
    ) {}

    /**
     * Execute an agent task and return a result.
     */
    abstract public function execute(AgentTask $task): AgentResult;

    /**
     * Call the Anthropic API with this agent's persona context.
     */
    protected function call(
        array $messages,
        ?string $purpose = null,
        ?string $pipelineRunId = null,
        ?string $model = null,
        ?float $temperature = null,
        ?int $maxTokens = null,
    ): array {
        return $this->client->createMessage(
            params: [
                'model'       => $model ?? $this->persona->getFullModel(),
                'max_tokens'  => $maxTokens ?? $this->persona->getMaxTokens(),
                'system'      => $this->persona->system_prompt,
                'messages'    => $messages,
                'temperature' => $temperature ?? $this->persona->getTemperature(),
                '_purpose'    => $purpose ?? 'agent_call',
            ],
            pipelineRunId: $pipelineRunId,
            persona: $this->persona,
        );
    }

    /**
     * Helper to extract text from response.
     */
    protected function extractText(array $response): string
    {
        return $this->client->extractTextContent($response);
    }
}
