<?php

namespace App\Services\Competitor;

use App\Models\CompetitorAlert;
use App\Models\CompetitorAlertEvent;
use App\Models\CompetitorContentItem;
use App\Notifications\CompetitorAlertNotification;
use App\Services\Competitor\Alerts\SlackChannel;
use App\Services\Competitor\Alerts\WebhookChannel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CompetitorAlertService
{
    public function __construct(
        private readonly SlackChannel $slack,
        private readonly WebhookChannel $webhook,
    ) {}

    /**
     * Evaluate all active alerts against a newly-crawled competitor content item.
     */
    public function evaluate(CompetitorContentItem $item): void
    {
        $source = $item->source;
        if (! $source) {
            return;
        }

        $alerts = CompetitorAlert::where('space_id', $source->space_id)
            ->where('is_active', true)
            ->get();

        foreach ($alerts as $alert) {
            if ($this->matches($alert, $item)) {
                $this->fire($alert, $item);
            }
        }
    }

    /**
     * Check whether an alert's conditions match a competitor content item.
     */
    public function matches(CompetitorAlert $alert, CompetitorContentItem $item): bool
    {
        $conditions = $alert->conditions ?? [];

        return match ($alert->type) {
            'new_content' => $this->matchesNewContent($conditions, $item),
            'keyword' => $this->matchesKeyword($conditions, $item),
            'high_similarity' => $this->matchesHighSimilarity($conditions, $item),
            default => false,
        };
    }

    /**
     * Fire an alert: record the event and dispatch notifications.
     */
    public function fire(CompetitorAlert $alert, CompetitorContentItem $item): CompetitorAlertEvent
    {
        // Deduplicate: skip if already notified for this (alert, item) pair
        $existing = CompetitorAlertEvent::where('alert_id', $alert->id)
            ->where('competitor_content_id', $item->id)
            ->whereNotNull('notified_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        $event = CompetitorAlertEvent::create([
            'alert_id' => $alert->id,
            'competitor_content_id' => $item->id,
            'trigger_data' => [
                'alert_type' => $alert->type,
                'conditions' => $alert->conditions,
                'fired_at' => now()->toIso8601String(),
            ],
            'notified_at' => now(),
        ]);

        $this->dispatch($alert, $event, $item);

        return $event;
    }

    // ─────────────────────────────────────────────────────────
    // Condition matchers
    // ─────────────────────────────────────────────────────────

    /** @param array<string, mixed> $conditions */
    private function matchesNewContent(array $conditions, CompetitorContentItem $item): bool
    {
        // If source_id restriction is set, ensure the item belongs to that source
        if (isset($conditions['source_id']) && $item->source_id !== $conditions['source_id']) {
            return false;
        }

        // Only fire once per item (already crawled_at set means it's not new)
        return $item->crawled_at !== null &&
            $item->crawled_at->diffInMinutes(now()) <= 10;
    }

    /** @param array<string, mixed> $conditions */
    private function matchesKeyword(array $conditions, CompetitorContentItem $item): bool
    {
        $keywords = $conditions['keywords'] ?? [];
        if (empty($keywords)) {
            return false;
        }

        $searchText = mb_strtolower(($item->title ?? '').' '.($item->excerpt ?? '').' '.($item->body ?? ''));

        foreach ($keywords as $keyword) {
            if (str_contains($searchText, mb_strtolower((string) $keyword))) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $conditions */
    private function matchesHighSimilarity(array $conditions, CompetitorContentItem $item): bool
    {
        $threshold = (float) ($conditions['similarity_threshold'] ?? 0.7);

        return $item->differentiationAnalyses()
            ->where('similarity_score', '>=', $threshold)
            ->exists();
    }

    // ─────────────────────────────────────────────────────────
    // Notification dispatch
    // ─────────────────────────────────────────────────────────

    private function dispatch(
        CompetitorAlert $alert,
        CompetitorAlertEvent $event,
        CompetitorContentItem $item,
    ): void {
        $channels = $alert->notify_channels ?? [];

        // Email
        if (! empty($channels['email'])) {
            $emails = is_array($channels['email']) ? $channels['email'] : [$channels['email']];
            foreach ($emails as $email) {
                try {
                    Notification::route('mail', $email)
                        ->notify(new CompetitorAlertNotification($alert, $event, $item));
                } catch (\Throwable $e) {
                    Log::warning('CompetitorAlert email dispatch failed', [
                        'alert_id' => $alert->id,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Slack
        if (! empty($channels['slack_webhook'])) {
            $this->slack->send($alert, $event, $item);
        }

        // Generic webhook
        if (! empty($channels['webhook_url'])) {
            $this->webhook->send($alert, $event, $item);
        }
    }
}
