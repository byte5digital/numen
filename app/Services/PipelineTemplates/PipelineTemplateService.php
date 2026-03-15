<?php

namespace App\Services\PipelineTemplates;

use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateVersion;
use App\Models\Space;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PipelineTemplateService
{
    public function __construct(
        private readonly TemplateSchemaValidator $validator,
    ) {}

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    public function create(Space $space, array $data): PipelineTemplate
    {
        $slug = $data['slug'] ?? Str::slug($data['name'] ?? '');

        return PipelineTemplate::create([
            'space_id' => $space->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($slug),
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'icon' => $data['icon'] ?? null,
            'schema_version' => $data['schema_version'] ?? '1.0',
            'is_published' => false,
            'author_name' => $data['author_name'] ?? null,
            'author_url' => $data['author_url'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(PipelineTemplate $template, array $data): PipelineTemplate
    {
        $fillable = ['name', 'description', 'category', 'icon', 'author_name', 'author_url'];
        $updates = array_intersect_key($data, array_flip($fillable));

        if (isset($data['slug'])) {
            $updates['slug'] = $data['slug'] === $template->slug
                ? $template->slug
                : $this->uniqueSlug($data['slug'], $template->id);
        }

        $template->update($updates);

        return $template->refresh();
    }

    public function delete(PipelineTemplate $template): void
    {
        $template->delete();
    }

    public function publish(PipelineTemplate $template): void
    {
        $template->update(['is_published' => true, 'space_id' => null]);
    }

    public function unpublish(PipelineTemplate $template): void
    {
        $template->update(['is_published' => false]);
    }

    // -------------------------------------------------------------------------
    // Version management
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $definition */
    public function createVersion(
        PipelineTemplate $template,
        array $definition,
        string $version,
        ?string $changelog = null,
    ): PipelineTemplateVersion {
        $result = $this->validator->validate($definition);

        if (! $result->isValid()) {
            throw new InvalidArgumentException(
                'Template definition is invalid: '.implode('; ', $result->errors()),
            );
        }

        return DB::transaction(function () use ($template, $definition, $version, $changelog): PipelineTemplateVersion {
            $template->versions()->where('is_latest', true)->update(['is_latest' => false]);

            return PipelineTemplateVersion::create([
                'template_id' => $template->id,
                'version' => $version,
                'definition' => $definition,
                'changelog' => $changelog,
                'is_latest' => true,
                'published_at' => now(),
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Import / Export
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function export(PipelineTemplate $template): array
    {
        /** @var PipelineTemplateVersion|null $latestVersion */
        $latestVersion = $template->versions()->where('is_latest', true)->first();

        return [
            'numen_export' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'template' => [
                'name' => $template->name,
                'slug' => $template->slug,
                'description' => $template->description,
                'category' => $template->category,
                'icon' => $template->icon,
                'schema_version' => $template->schema_version,
                'author_name' => $template->author_name,
                'author_url' => $template->author_url,
            ],
            'version' => $latestVersion ? [
                'version' => $latestVersion->version,
                'changelog' => $latestVersion->changelog,
                'definition' => $latestVersion->definition,
            ] : null,
        ];
    }

    /** @param array<string, mixed> $data */
    public function import(Space $space, array $data): PipelineTemplate
    {
        if (! isset($data['template'])) {
            throw new InvalidArgumentException('Import data is missing the "template" key.');
        }

        return DB::transaction(function () use ($space, $data): PipelineTemplate {
            $templateData = $data['template'];
            $template = $this->create($space, $templateData);

            if (isset($data['version']) && is_array($data['version'])) {
                $v = $data['version'];
                $this->createVersion(
                    $template,
                    $v['definition'] ?? [],
                    $v['version'] ?? '1.0.0',
                    $v['changelog'] ?? null,
                );
            }

            return $template->refresh();
        });
    }

    public function exportToFile(PipelineTemplate $template): string
    {
        $payload = $this->export($template);
        $filename = 'pipeline-templates/'.$template->slug.'-'.now()->format('Ymd_His').'.json';

        Storage::put($filename, (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return Storage::path($filename);
    }

    public function importFromFile(Space $space, string $path): PipelineTemplate
    {
        if (! file_exists($path)) {
            throw new RuntimeException("Import file not found: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read file: {$path}");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $this->import($space, $data);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function uniqueSlug(string $base, ?string $excludeId = null): string
    {
        $slug = $base !== '' ? $base : 'template';
        $count = 0;
        $candidate = $slug;

        do {
            $query = PipelineTemplate::withTrashed()->where('slug', $candidate);
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }
            $exists = $query->exists();

            if ($exists) {
                $count++;
                $candidate = $slug.'-'.$count;
            }
        } while ($exists);

        return $candidate;
    }
}
