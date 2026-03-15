<?php

namespace App\Services;

use App\Models\Content;
use App\Models\ContentTranslationJob;
use App\Models\Persona;
use App\Models\Space;

class TranslationService
{
    /**
     * Create a new translation job for the given source content and target locale.
     *
     * If a pending or processing job already exists for the same source + target,
     * the existing job is returned without creating a duplicate.
     *
     * @throws \InvalidArgumentException When the source content has no locale set.
     */
    public function createTranslationJob(
        Content $source,
        string $targetLocale,
        ?Persona $persona = null,
    ): ContentTranslationJob {
        if (empty($source->locale)) {
            throw new \InvalidArgumentException(
                "Source content #{$source->id} has no locale — cannot create translation job.",
            );
        }

        // Return existing active job instead of creating a duplicate
        $existing = ContentTranslationJob::where('source_content_id', $source->id)
            ->where('target_locale', $targetLocale)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return ContentTranslationJob::create([
            'space_id' => $source->space_id,
            'source_content_id' => $source->id,
            'target_content_id' => null,
            'source_locale' => $source->locale,
            'target_locale' => $targetLocale,
            'status' => 'pending',
            'persona_id' => $persona?->id,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Get the translation status for a content item in a specific locale.
     *
     * Returns the most recent job status string (pending|processing|completed|failed),
     * or null when no job exists.
     */
    public function getTranslationStatus(Content $content, string $targetLocale): ?string
    {
        return ContentTranslationJob::where('source_content_id', $content->id)
            ->where('target_locale', $targetLocale)
            ->latest()
            ->value('status');
    }

    /**
     * Build a matrix of translation statuses for all content in a space.
     *
     * Returns an array keyed by source content ID, with nested locale => status strings.
     * Only locales that have at least one job are included.
     *
     * @return array<string, array<string, string>>
     */
    public function getTranslationMatrix(Space $space): array
    {
        $jobs = ContentTranslationJob::where('space_id', $space->id)
            ->orderByDesc('created_at')
            ->get(['source_content_id', 'target_locale', 'status']);

        $matrix = [];

        foreach ($jobs as $job) {
            $contentId = (string) $job->source_content_id;

            // Only keep the most recent status per content + locale pair
            if (! isset($matrix[$contentId][$job->target_locale])) {
                $matrix[$contentId][$job->target_locale] = $job->status;
            }
        }

        return $matrix;
    }

    /**
     * Cancel a pending or processing translation job.
     *
     * Jobs that are already completed or failed are left unchanged.
     */
    public function cancelJob(ContentTranslationJob $job): void
    {
        if (! in_array($job->status, ['pending', 'processing'], true)) {
            return;
        }

        $job->update([
            'status' => 'failed',
            'error_message' => 'Job cancelled by user.',
            'completed_at' => now(),
        ]);
    }

    /**
     * Retry a failed translation job by resetting it to pending.
     *
     * @throws \RuntimeException When the job is not in a failed state.
     */
    public function retryJob(ContentTranslationJob $job): void
    {
        if ($job->status !== 'failed') {
            throw new \RuntimeException(
                "Only failed jobs can be retried. Job #{$job->id} has status '{$job->status}'.",
            );
        }

        $job->update([
            'status' => 'pending',
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'target_content_id' => null,
        ]);
    }
}
