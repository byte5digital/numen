<?php

namespace App\Jobs;

use App\Agents\AgentFactory;
use App\Agents\AgentTask;
use App\Models\ContentType;
use App\Models\PipelineRun;
use App\Pipelines\PipelineExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAgentStage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 240; // AI calls can take 2+ minutes

    public int $maxExceptions = 3;

    public function __construct(
        public PipelineRun $run,
        public array $stage,
    ) {
        $this->onQueue('ai-pipeline');
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function handle(AgentFactory $agentFactory, PipelineExecutor $executor): void
    {
        Log::info('Running agent stage', [
            'run_id' => $this->run->id,
            'stage' => $this->stage['name'],
            'type' => $this->stage['type'],
        ]);

        try {
            $spaceId = $this->run->context['space_id'] ?? null;
            $brief = $this->run->context['brief'] ?? [];

            // Determine agent role from stage config
            $role = $this->stage['agent_role'] ?? match ($this->stage['type']) {
                'ai_generate' => 'creator',
                'ai_transform' => 'optimizer',
                'ai_review' => 'reviewer',
                default => 'creator',
            };

            $agent = $agentFactory->makeByRole($spaceId, $role);

            // Build task context based on stage type
            $context = $this->buildTaskContext();

            $task = new AgentTask(
                type: $this->stage['type'],
                context: $context,
                pipelineRunId: $this->run->id,
            );

            $result = $agent->execute($task);

            if (! $result->success) {
                Log::warning('Agent stage failed', [
                    'run_id' => $this->run->id,
                    'stage' => $this->stage['name'],
                    'reason' => $result->text,
                ]);
                $this->run->markFailed($result->text);

                return;
            }

            // Store result in pipeline context for next stage
            $context = $this->run->context ?? [];
            $context['last_stage_output'] = $result->data;
            $context['last_stage_text'] = $result->text;
            $context['last_stage_score'] = $result->score;

            // If this was a generation stage, create the content + version
            if ($this->stage['type'] === 'ai_generate') {
                $this->createContentFromResult($result, $context);
            }

            // If this was SEO optimization, update seo_data on the version
            if ($this->stage['type'] === 'ai_transform' && $this->run->content_id) {
                $this->applySeoOptimization($result);
            }

            // If this was a review stage, save quality_score to the current version
            if ($this->stage['type'] === 'ai_review' && $this->run->content_id) {
                $version = $this->run->content?->currentVersion;
                if ($version) {
                    $version->update(['quality_score' => $result->score]);
                }
            }

            $this->run->update(['context' => $context]);

            // Advance pipeline
            $executor->advance($this->run, [
                'stage' => $this->stage['name'],
                'success' => true,
                'score' => $result->score,
                'summary' => substr($result->text ?? '', 0, 500),
            ]);

        } catch (\App\Services\AI\Exceptions\CostLimitExceededException $e) {
            Log::warning('Agent stage blocked by cost limit — not retrying', [
                'run_id' => $this->run->id,
                'stage' => $this->stage['name'],
                'period' => $e->period,
            ]);
            $this->run->markFailed("Cost limit exceeded: {$e->getMessage()}");
            $this->fail($e);

            return;
        } catch (\Throwable $e) {
            Log::error('Agent stage exception', [
                'run_id' => $this->run->id,
                'stage' => $this->stage['name'],
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->run->markFailed("Stage {$this->stage['name']} failed after {$this->tries} attempts: {$e->getMessage()}");
            }

            throw $e;
        }
    }

    private function buildTaskContext(): array
    {
        $brief = $this->run->context['brief'] ?? [];
        $lastOutput = $this->run->context['last_stage_output'] ?? [];

        $contentType = [];
        if (! empty($brief['content_type_slug'])) {
            $type = ContentType::where('slug', $brief['content_type_slug'])->first();
            $contentType = $type ? $type->toArray() : [];
        }

        // In update mode, pass existing content to the generation stage
        $existingContent = $this->run->context['existing_content'] ?? null;

        return match ($this->stage['type']) {
            'ai_generate' => array_filter([
                'brief' => $brief,
                'content_type' => $contentType,
                'existing_content' => $existingContent,
            ]),
            'ai_transform' => [
                'content' => $lastOutput,
                'target_keywords' => $brief['target_keywords'] ?? [],
                'brief' => $brief,
            ],
            'ai_review' => [
                'content' => $this->getContentForReview(),
                'brief' => $brief,
                'brand_guidelines' => $this->run->content?->space?->settings['brand_guidelines'] ?? '',
            ],
            default => [
                'brief' => $brief,
                'last_output' => $lastOutput,
            ],
        };
    }

    private function getContentForReview(): array
    {
        $content = $this->run->content;
        if (! $content?->currentVersion) {
            return $this->run->context['last_stage_output'] ?? [];
        }

        $version = $content->currentVersion;

        return [
            'title' => $version->title,
            'excerpt' => $version->excerpt ?? '',
            'body' => $version->body,
        ];
    }

    private function createContentFromResult($result, array &$context): void
    {
        $brief = $this->run->context['brief'] ?? [];
        $data = $result->data;
        $isUpdate = $this->run->context['update_mode'] ?? false;

        // Update mode: create a new version on the existing content
        if ($isUpdate && $this->run->content_id) {
            $content = $this->run->content;
            $latestVersion = $content->versions()->orderByDesc('version_number')->first();
            $nextVersionNumber = ($latestVersion->version_number ?? 0) + 1;

            $version = \App\Models\ContentVersion::create([
                'content_id' => $content->id,
                'version_number' => $nextVersionNumber,
                'title' => $data['title'] ?? $latestVersion->title ?? $brief['title'],
                'excerpt' => $data['excerpt'] ?? $latestVersion->excerpt ?? '',
                'body' => $data['body'] ?? $result->text,
                'body_format' => 'markdown',
                'author_type' => 'ai_agent',
                'author_id' => $this->stage['persona_id'] ?? 'system',
                'change_reason' => 'update_brief: '.($brief['description'] ?? 'AI update'),
                'pipeline_run_id' => $this->run->id,
            ]);

            $content->update([
                'current_version_id' => $version->id,
                'status' => 'in_pipeline',
                'taxonomy' => array_merge($content->taxonomy ?? [], ['tags' => $data['tags'] ?? $content->taxonomy['tags'] ?? []]),
            ]);

            $context['content_id'] = $content->id;
            $context['last_stage_output'] = $data;

            return;
        }

        // Normal mode: create new content
        $content = \App\Models\Content::create([
            'space_id' => $brief['space_id'],
            'content_type_id' => ContentType::where('slug', $brief['content_type_slug'] ?? 'blog_post')->first()?->id,
            'slug' => \Illuminate\Support\Str::slug($data['title'] ?? $brief['title']),
            'status' => 'in_pipeline',
            'locale' => $brief['target_locale'] ?? 'en',
            'taxonomy' => ['tags' => $data['tags'] ?? []],
        ]);

        $version = \App\Models\ContentVersion::create([
            'content_id' => $content->id,
            'version_number' => 1,
            'title' => $data['title'] ?? $brief['title'],
            'excerpt' => $data['excerpt'] ?? '',
            'body' => $data['body'] ?? $result->text,
            'body_format' => 'markdown',
            'author_type' => 'ai_agent',
            'author_id' => $this->stage['persona_id'] ?? 'system',
            'change_reason' => 'initial_generation',
            'pipeline_run_id' => $this->run->id,
        ]);

        $content->update(['current_version_id' => $version->id]);
        $this->run->update(['content_id' => $content->id]);

        $context['content_id'] = $content->id;
        $context['last_stage_output'] = $data;
    }

    private function applySeoOptimization($result): void
    {
        $content = $this->run->content;
        if (! $content?->currentVersion) {
            return;
        }

        $seoData = $result->data;
        $version = $content->currentVersion;

        $updateData = ['seo_data' => $seoData, 'seo_score' => $result->score];

        // If SEO agent provided an optimized body, create a new version
        if (! empty($seoData['optimized_body'])) {
            \App\Models\ContentVersion::create([
                'content_id' => $content->id,
                'version_number' => $version->version_number + 1,
                'title' => $seoData['seo_title'] ?? $version->title,
                'excerpt' => $version->excerpt,
                'body' => $seoData['optimized_body'],
                'body_format' => 'markdown',
                'seo_data' => $seoData,
                'seo_score' => $result->score,
                'author_type' => 'ai_agent',
                'author_id' => $this->stage['persona_id'] ?? 'seo_agent',
                'change_reason' => 'seo_optimization',
                'pipeline_run_id' => $this->run->id,
            ]);
        } else {
            $version->update($updateData);
        }
    }
}
