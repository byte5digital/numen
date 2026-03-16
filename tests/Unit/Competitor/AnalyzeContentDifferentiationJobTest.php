<?php

namespace Tests\Unit\Competitor;

use App\Jobs\AnalyzeContentDifferentiationJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyzeContentDifferentiationJobTest extends TestCase
{
    public function test_job_is_on_competitor_queue(): void
    {
        Queue::fake();

        $contentId = '01HWXXXXXXXXXXXXXXXXXXXXXXX';
        $job = new AnalyzeContentDifferentiationJob($contentId);

        $this->assertSame('competitor', $job->queue);
        $this->assertSame($contentId, $job->contentId);
        $this->assertSame(3, $job->tries);
    }

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        $contentId = '01HWXXXXXXXXXXXXXXXXXXXXXXX';
        AnalyzeContentDifferentiationJob::dispatch($contentId);

        Queue::assertPushed(AnalyzeContentDifferentiationJob::class, function ($job) use ($contentId) {
            return $job->contentId === $contentId;
        });
    }

    public function test_job_is_on_correct_queue_when_dispatched(): void
    {
        Queue::fake();

        AnalyzeContentDifferentiationJob::dispatch('some-content-id');

        Queue::assertPushedOn('competitor', AnalyzeContentDifferentiationJob::class);
    }
}
