<?php

namespace App\Console\Commands\Content;

use App\Models\Content;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;

class ContentExportCommand extends Command
{
    protected $signature = 'numen:content:export
        {--format=json : Export format (json or markdown)}
        {--output= : Output file path (defaults to stdout)}
        {--type= : Filter by content type slug}
        {--status= : Filter by status}
        {--id= : Export a single content item by ID}';

    protected $description = 'Export content to JSON or markdown';

    public function handle(): int
    {
        $query = Content::query()->with(['contentType', 'currentVersion']);

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        } else {
            if ($type = $this->option('type')) {
                $query->whereHas('contentType', fn ($q) => $q->where('slug', $type));
            }

            if ($status = $this->option('status')) {
                $query->where('status', $status);
            }
        }

        /** @var Collection<int, Content> $contents */
        $contents = $query->get();

        if ($contents->isEmpty()) {
            $this->warn('No content items matched the given filters.');

            return self::SUCCESS;
        }

        $format = strtolower((string) ($this->option('format') ?? 'json'));

        if (! in_array($format, ['json', 'markdown'])) {
            $this->error("Invalid format '{$format}'. Use 'json' or 'markdown'.");

            return self::FAILURE;
        }

        $output = $format === 'json'
            ? $this->toJson($contents)
            : $this->toMarkdown($contents);

        $outputPath = $this->option('output');

        if ($outputPath) {
            File::put($outputPath, $output);
            $this->info("Exported {$contents->count()} item(s) to {$outputPath}");
        } else {
            $this->line($output);
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Content>  $contents
     */
    private function toJson(Collection $contents): string
    {
        $data = $contents->map(function (Content $c): array {
            return [
                'id' => $c->id,
                'slug' => $c->slug,
                'status' => $c->status,
                'locale' => $c->locale,
                'content_type' => $c->contentType->slug,
                'title' => $c->currentVersion?->title,
                'excerpt' => $c->currentVersion?->excerpt,
                'body' => $c->currentVersion?->body,
                'seo_data' => $c->currentVersion?->seo_data,
                'published_at' => $c->published_at?->toIso8601String(),
                'created_at' => $c->created_at->toIso8601String(),
                'updated_at' => $c->updated_at->toIso8601String(),
            ];
        });

        return json_encode($data->values(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  Collection<int, Content>  $contents
     */
    private function toMarkdown(Collection $contents): string
    {
        $parts = [];

        foreach ($contents as $content) {
            $version = $content->currentVersion;
            $title = $version !== null ? $version->title : $content->slug;
            $body = $version !== null ? ($version->body ?? '') : '';

            $parts[] = implode("\n", [
                "# {$title}",
                '',
                "**Slug:** {$content->slug}  ",
                "**Status:** {$content->status}  ",
                "**Locale:** {$content->locale}  ",
                '**Type:** '.$content->contentType->slug,
                '',
                $body,
                '',
                '---',
                '',
            ]);
        }

        return implode("\n", $parts);
    }
}
