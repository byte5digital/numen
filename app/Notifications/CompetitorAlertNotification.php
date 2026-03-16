<?php

namespace App\Notifications;

use App\Models\CompetitorAlert;
use App\Models\CompetitorAlertEvent;
use App\Models\CompetitorContentItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompetitorAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CompetitorAlert $alert,
        public readonly CompetitorAlertEvent $event,
        public readonly CompetitorContentItem $competitorContent,
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $source = $this->competitorContent->source;

        return (new MailMessage)
            ->subject("[Numen] Competitor Alert: {$this->alert->name}")
            ->greeting('Competitor Activity Detected')
            ->line("Alert **{$this->alert->name}** was triggered.")
            ->line('**Source:** '.($source !== null ? $source->name : 'Unknown'))
            ->line('**Article:** '.($this->competitorContent->title ?? $this->competitorContent->external_url))
            ->line('**Published:** '.($this->competitorContent->published_at?->toDateTimeString() ?? 'Unknown'))
            ->action('View Dashboard', url('/admin/competitors'))
            ->line('This notification was sent by Numen Competitor Monitoring.');
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'alert_name' => $this->alert->name,
            'event_id' => $this->event->id,
            'competitor_content_id' => $this->competitorContent->id,
            'competitor_title' => $this->competitorContent->title,
        ];
    }
}
