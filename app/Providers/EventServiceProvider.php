<?php

namespace App\Providers;

use App\GraphQL\Events\ContentPublishedEvent;
use App\GraphQL\Events\PipelineRunUpdatedEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Nuwave\Lighthouse\Execution\Utils\Subscription;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        \App\Events\Quality\ContentQualityScored::class => [
            \App\Listeners\QualityScoredWebhookListener::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();

        \Illuminate\Support\Facades\Event::listen(
            ContentPublishedEvent::class,
            function (ContentPublishedEvent $event): void {
                Subscription::broadcast('contentPublished', $event->content);
                Subscription::broadcast('contentUpdated', $event->content);
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            PipelineRunUpdatedEvent::class,
            function (PipelineRunUpdatedEvent $event): void {
                Subscription::broadcast('pipelineRunUpdated', $event->pipelineRun);
                Subscription::broadcast('pipelineRunCompleted', $event->pipelineRun);
            }
        );
    }
}
