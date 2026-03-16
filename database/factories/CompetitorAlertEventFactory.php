<?php

namespace Database\Factories;

use App\Models\CompetitorAlert;
use App\Models\CompetitorAlertEvent;
use App\Models\CompetitorContentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompetitorAlertEventFactory extends Factory
{
    protected $model = CompetitorAlertEvent::class;

    public function definition(): array
    {
        return [
            'alert_id' => CompetitorAlert::factory(),
            'competitor_content_id' => CompetitorContentItem::factory(),
            'trigger_data' => [],
            'notified_at' => null,
        ];
    }

    public function notified(): static
    {
        return $this->state(['notified_at' => now()]);
    }
}
