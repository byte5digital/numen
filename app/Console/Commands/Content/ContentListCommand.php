<?php

namespace App\Console\Commands\Content;

use App\Models\Content;
use Illuminate\Console\Command;

class ContentListCommand extends Command
{
    protected $signature = 'numen:content:list
        {--type= : Filter by content type slug}
        {--status= : Filter by status (draft, published, archived)}
        {--limit=20 : Number of items to show}';

    protected $description = 'List content items with optional filters';

    public function handle(): int
    {
        $query = Content::query()
            ->with(['contentType', 'currentVersion'])
            ->orderByDesc('created_at')
            ->limit((int) $this->option('limit'));

        if ($type = $this->option('type')) {
            $query->whereHas('contentType', fn ($q) => $q->where('slug', $type));
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Content> $contents */
        $contents = $query->get();

        if ($contents->isEmpty()) {
            $this->info('No content items found.');

            return self::SUCCESS;
        }

        $rows = $contents->map(function (Content $c): array {
            return [
                $c->id,
                $c->slug,
                $c->contentType ? $c->contentType->slug : '—',
                $c->status,
                $c->locale,
                $c->created_at->format('Y-m-d'),
            ];
        });

        $this->table(
            ['ID', 'Slug', 'Type', 'Status', 'Locale', 'Created'],
            $rows
        );

        $this->line("Showing {$contents->count()} item(s).");

        return self::SUCCESS;
    }
}
