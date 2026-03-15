<?php

namespace App\Jobs;

use App\Events\TranslationCompleted;
use App\Events\TranslationFailed;
use App\Models\Content;
use App\Models\ContentTranslationJob;
use App\Services\AITranslationService;
use App\Services\TranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TranslateContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'ai-pipeline';

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly ContentTranslationJob $translationJob,
    ) {}

    public function handle(AITranslationService $aiService, TranslationService $translationService): void
    {
        $job = $this->translationJob;

        // 1. Mark as processing
        $job->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            $source = $job->sourceContent;
            $persona = $job->persona;

            // 2. Call AI translation
            $translated = $aiService->translate($source, $job->target_locale, $persona);

            // 3. Clone source Content with translated fields + target locale
            $translatedContent = DB::transaction(function () use ($source, $job, $translated) {
                /** @var Content $newContent */
                $newContent = Content::create([
                    'space_id' => $source->space_id,
                    'content_type_id' => $source->content_type_id,
                    'slug' => $source->slug.'-'.$job->target_locale,
                    'status' => 'draft',
                    'locale' => $job->target_locale,
                    'canonical_id' => $source->canonical_id ?? $source->id,
                    'taxonomy' => $source->taxonomy,
                    'metadata' => $source->metadata,
                    'hero_image_id' => $source->hero_image_id,
                ]);

                // Create an initial version with translated fields
                if ($source->currentVersion) {
                    $sourceVersion = $source->currentVersion;
                    $newVersion = $newContent->versions()->create([
                        'space_id' => $source->space_id,
                        'title' => $translated['title'],
                        'body' => $translated['body'],
                        'excerpt' => $translated['excerpt'],
                        'meta_description' => $translated['meta_description'],
                        'schema_snapshot' => $sourceVersion->schema_snapshot ?? null,
                        'field_values' => $sourceVersion->field_values ?? null,
                        'created_by' => $sourceVersion->created_by ?? null,
                    ]);

                    $newContent->update(['current_version_id' => $newVersion->id]);
                }

                return $newContent;
            });

            // 4. Mark job as completed
            $job->update([
                'status' => 'completed',
                'target_content_id' => $translatedContent->id,
                'completed_at' => now(),
                'error_message' => null,
            ]);

            // 5. Fire success event
            event(new TranslationCompleted($job, $translatedContent));

        } catch (\Throwable $e) {
            Log::error('TranslateContentJob failed', [
                'translation_job_id' => $job->id,
                'source_content_id' => $job->source_content_id,
                'target_locale' => $job->target_locale,
                'error' => $e->getMessage(),
            ]);

            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            event(new TranslationFailed($job, $e->getMessage()));

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->translationJob->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
