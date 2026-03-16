<?php

namespace App\Services\Competitor\Alerts;

use App\Models\CompetitorAlert;
use App\Models\CompetitorAlertEvent;
use App\Models\CompetitorContentItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookChannel
{
    public function send(
        CompetitorAlert $alert,
        CompetitorAlertEvent $event,
        CompetitorContentItem $item,
    ): void {
        $channels = $alert->notify_channels ?? [];
        $webhookUrl = $channels['webhook_url'] ?? null;

        if (! $webhookUrl) {
            return;
        }

        $payload = [
            'event' => 'competitor_alert',
            'alert' => [
                'id' => $alert->id,
                'name' => $alert->name,
                'type' => $alert->type,
            ],
            'alert_event' => [
                'id' => $event->id,
                'triggered_at' => now()->toIso8601String(),
                'trigger_data' => $event->trigger_data,
            ],
            'competitor_content' => [
                'id' => $item->id,
                'title' => $item->title,
                'url' => $item->external_url,
                'published_at' => $item->published_at?->toIso8601String(),
                'source_name' => $item->source !== null ? $item->source->name : null,
            ],
        ];

        try {
            Http::timeout(10)->post($webhookUrl, $payload);
        } catch (\Throwable $e) {
            Log::warning('CompetitorAlert webhook send failed', [
                'alert_id' => $alert->id,
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
