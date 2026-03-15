<?php

namespace App\Services\Chat;

use App\Models\Space;
use App\Models\User;

class SystemPromptBuilder
{
    /**
     * Build a context-aware system prompt for the LLM.
     */
    public function build(Space $space, User $user): string
    {
        $spaceName = $space->name;
        $userName = $user->name;
        $currentDate = now()->toDateString();

        $actions = $this->buildAvailableActions($user, $space);
        $actionsJson = json_encode($actions, JSON_PRETTY_PRINT);

        $contentTypeNames = $space->contentTypes->pluck('name')->implode(', ') ?: 'none';
        $pipelineNames = $space->pipelines->pluck('name')->implode(', ') ?: 'none';
        $personaNames = $space->personas->pluck('name')->implode(', ') ?: 'none';

        return <<<PROMPT
            You are a CMS assistant for the "{$spaceName}" space, helping {$userName} manage their content.
            Today is {$currentDate}.

            SPACE CONTEXT:
            - Content types available: {$contentTypeNames}
            - Pipelines available: {$pipelineNames}
            - Personas available: {$personaNames}

            You have access to the following actions based on the user's permissions:
            {$actionsJson}

            RESPONSE FORMAT (always respond with valid JSON):
            {
              "message": "Human-readable response to the user",
              "intent": {
                "action": "one of the available actions or null",
                "entity": "content|pipeline|null",
                "params": {},
                "confidence": 0.0,
                "requires_confirmation": false
              }
            }

            RULES:
            1. Always respond with valid JSON matching the format above.
            2. If no action is needed, set "action" to "query.generic" and "requires_confirmation" to false.
            3. For destructive actions (delete, publish), set "requires_confirmation" to true unless the user explicitly confirmed.
            4. Keep "message" conversational and helpful.
            5. Set "confidence" between 0.0 and 1.0 based on how certain you are about the intent.
            6. Never perform actions the user doesn't have permission for.
            7. If asked to do something not in the available actions, explain politely that you don't have permission.
            8. When creating content, prefer the content types available in this space.
            9. When triggering pipelines, use the pipeline names listed above.
            PROMPT;
    }

    /**
     * Build list of available actions based on user permissions.
     *
     * @return array<string, string>
     */
    private function buildAvailableActions(User $user, Space $space): array
    {
        $actions = ['query.generic' => 'Answer general questions about content in the space'];

        if ($user->isAdmin() || $user->hasPermission('content.view', $space->id)) {
            $actions['content.query'] = 'Query and list content items with filters';
        }

        if ($user->isAdmin() || $user->hasPermission('content.create', $space->id)) {
            $actions['content.create'] = 'Create new content or a content brief';
        }

        if ($user->isAdmin() || $user->hasPermission('content.update', $space->id)) {
            $actions['content.update'] = 'Update existing content fields';
        }

        if ($user->isAdmin() || $user->hasPermission('content.delete', $space->id)) {
            $actions['content.delete'] = 'Delete content (requires confirmation)';
        }

        if ($user->isAdmin() || $user->hasPermission('content.publish', $space->id)) {
            $actions['content.publish'] = 'Publish content (requires confirmation)';
            $actions['content.unpublish'] = 'Unpublish / archive content (requires confirmation)';
        }

        if ($user->isAdmin() || $user->hasPermission('pipeline.trigger', $space->id)) {
            $actions['pipeline.trigger'] = 'Trigger a content pipeline run';
        }

        return $actions;
    }
}
