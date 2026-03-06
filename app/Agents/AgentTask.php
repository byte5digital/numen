<?php

namespace App\Agents;

class AgentTask
{
    public function __construct(
        public readonly string $type,
        public readonly array $context = [],
        public readonly ?string $pipelineRunId = null,
    ) {}
}
