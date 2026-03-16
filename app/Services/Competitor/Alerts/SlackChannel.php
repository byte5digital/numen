<?php

namespace App\Services\Competitor\Alerts;

use App\Models\CompetitorAlert;
use App\Models\CompetitorAlertEvent;
use App\Models\CompetitorContentItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackChannel
{
    public function send(
        CompetitorAlert $alert,
        CompetitorAlertEvent $event,
        CompetitorContentItem $item,
    ): void {
        $channels = $alert->notify_channels ?? [];
        $webhookUrl = $channels['slack_webhook'] ?? null;

        if (! $webhookUrl) {
            return;
        }

        $source = $item->source;
        $payload = [
            'text' => "🔔 *Competitor Alert: {$alert->name}*",
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Competitor Alert Triggered: {$alert->name}*",
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Source:*\n".($source !== null ? $source->name : 'Unknown')],
                        ['type' => 'mrkdwn', 'text' => "*Type:*\n".$alert->type],
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Article:* <{$item->external_url}|".($item->title ?? $item->external_url).'>',
                    ],
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => ['type' => 'plain_text', 'text' => 'View Dashboard'],
                            'url' => url('/admin/competitors'),
                        ],
                    ],
                ],
            ],
        ];

        try {
            Http::post($webhookUrl, $payload);
        } catch (\Throwable $e) {
            Log::warning('CompetitorAlert Slack send failed', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
