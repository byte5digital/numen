<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Models\Space;
use App\Services\LocaleService;
use Illuminate\Console\Command;

class SetupI18nCommand extends Command
{
    protected $signature = 'numen:setup-i18n {space_id} {--locale=en} {--label=English}';

    protected $description = 'Set up i18n for a space - creates default locale and migrates existing content';

    public function handle(LocaleService $localeService): int
    {
        $spaceId = $this->argument('space_id');
        $locale = $this->option('locale');
        $label = $this->option('label');

        $space = Space::find($spaceId);

        if (! $space) {
            $this->error("Space not found: {$spaceId}");

            return Command::FAILURE;
        }

        $this->info("Setting up i18n for space: {$space->name} (ID: {$space->id})");

        $existing = $space->locales()->count();

        if ($existing > 0) {
            $this->warn("Space already has {$existing} locale(s) configured.");

            if (! $this->confirm('Continue and add the locale anyway?', false)) {
                return Command::SUCCESS;
            }
        }

        $spaceLocale = $localeService->addLocale(
            space: $space,
            locale: $locale,
            label: $label,
            isDefault: true,
        );

        $this->info("Created default locale: {$label} ({$locale})");

        $migrated = Content::where('space_id', $space->id)
            ->whereNull('locale')
            ->update(['locale' => $locale]);

        $this->info("Migrated {$migrated} content item(s) to locale '{$locale}'.");

        $this->table(
            ['Space', 'Locale', 'Label', 'Is Default', 'Content Migrated'],
            [[$space->name, $spaceLocale->locale, $spaceLocale->label, 'Yes', $migrated]],
        );

        return Command::SUCCESS;
    }
}
