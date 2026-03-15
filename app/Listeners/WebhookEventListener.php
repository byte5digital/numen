<?php

namespace App\Listeners;

use App\Services\Webhooks\WebhookEventDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Generic listener that forwards any domain event to the webhook dispatcher.
 *
 * Register this against specific event classes in EventServiceProvider,
 * or fire it manually via event(new NumenEvent($type, $spaceId, $context)).
 */
class WebhookEventListener implements ShouldQueue
{
    public function __construct(private readonly WebhookEventDispatcher $dispatcher) {}

    /**
     * Handle the event.
     *
     * Expects the event object to expose:
     *   - $event->eventType : string  e.g. "content.published"
     *   - $event->spaceId   : string  Space ULID
     *   - $event->context   : array   Model/payload data
     */
    public function handle(object $event): void
    {
        if (! property_exists($event, 'eventType') || ! property_exists($event, 'spaceId')) {
            return;
        }

        $this->dispatcher->dispatch(
            $event->eventType,
            $event->spaceId,
            $event->context ?? [],
        );
    }
}
