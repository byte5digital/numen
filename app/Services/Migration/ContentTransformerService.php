<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Models\Migration\MigrationTypeMapping;
use Illuminate\Support\Facades\Log;
use League\CommonMark\CommonMarkConverter;

/**
 * Transforms a single source content item into Numen-ready format
 * based on the field map defined in a MigrationTypeMapping.
 */
class ContentTransformerService
{
    /** @var array<string, callable(mixed, array<string, mixed>): mixed> */
    private array $customTransformers = [];

    /**
     * Register a custom transformer callback for a given field type.
     *
     * @param  callable(mixed, array<string, mixed>): mixed  $callback
     */
    public function registerTransformer(string $fieldType, callable $callback): static
    {
        $this->customTransformers[$fieldType] = $callback;

        return $this;
    }

    /**
     * Transform a single source content item using the given type mapping.
     *
     * @param  array<string, mixed>  $sourceContent
     * @return array{fields: array<string, mixed>, media_refs: list<string>, taxonomy_refs: list<string>}
     */
    public function transform(array $sourceContent, MigrationTypeMapping $mapping): array
    {
        /** @var list<array{source_field: string, target_field: string|null, source_type?: string, target_type?: string}> $fieldMap */
        $fieldMap = $mapping->field_map;
        $transformed = [];
        $mediaRefs = [];
        $taxonomyRefs = [];

        foreach ($fieldMap as $entry) {
            $sourceField = $entry['source_field'] ?? null;
            $targetField = $entry['target_field'] ?? null;

            if ($sourceField === null || $targetField === null) {
                continue;
            }

            $sourceValue = $sourceContent[$sourceField] ?? null;
            if ($sourceValue === null) {
                continue;
            }

            $sourceType = $entry['source_type'] ?? 'string';
            $targetType = $entry['target_type'] ?? $sourceType;

            if (isset($this->customTransformers[$sourceType])) {
                $transformed[$targetField] = ($this->customTransformers[$sourceType])($sourceValue, $entry);

                continue;
            }

            $result = $this->convertField($sourceValue, $sourceType, $targetType);
            $transformed[$targetField] = $result['value'];

            if (! empty($result['media_refs'])) {
                $mediaRefs = array_merge($mediaRefs, $result['media_refs']);
            }

            if (! empty($result['taxonomy_refs'])) {
                $taxonomyRefs = array_merge($taxonomyRefs, $result['taxonomy_refs']);
            }
        }

        return [
            'fields' => $transformed,
            'media_refs' => array_values(array_unique($mediaRefs)),
            'taxonomy_refs' => array_values(array_unique($taxonomyRefs)),
        ];
    }

    /**
     * @return array{value: mixed, media_refs: list<string>, taxonomy_refs: list<string>}
     */
    private function convertField(mixed $sourceValue, string $sourceType, string $targetType): array
    {
        $mediaRefs = [];
        $taxonomyRefs = [];

        $value = match ($sourceType) {
            'richtext', 'html' => $this->transformRichText($sourceValue, $mediaRefs),
            'markdown' => $this->transformMarkdown($sourceValue, $mediaRefs),
            'media', 'image', 'file' => $this->transformMedia($sourceValue, $mediaRefs),
            'relation', 'taxonomy', 'category', 'tag' => $this->transformRelation($sourceValue, $taxonomyRefs),
            'number', 'integer', 'float' => $this->transformNumber($sourceValue),
            'boolean' => $this->transformBoolean($sourceValue),
            'date', 'datetime' => $this->transformDate($sourceValue),
            'json', 'object' => $this->transformJson($sourceValue),
            default => $this->transformString($sourceValue),
        };

        return ['value' => $value, 'media_refs' => $mediaRefs, 'taxonomy_refs' => $taxonomyRefs];
    }

    /** @param  list<string>  $mediaRefs */
    private function transformRichText(mixed $value, array &$mediaRefs): string
    {
        if (is_array($value)) {
            $value = $this->blocksToHtml($value);
        }

        return $this->extractMediaFromHtml((string) $value, $mediaRefs);
    }

    /** @param  list<string>  $mediaRefs */
    private function transformMarkdown(mixed $value, array &$mediaRefs): string
    {
        $md = is_array($value) ? implode("\n\n", array_map('strval', $value)) : (string) $value;

        if (preg_match_all('/!\[[^\]]*\]\(([^)]+)\)/', $md, $matches)) {
            foreach ($matches[1] as $url) {
                $mediaRefs[] = $url;
            }
        }

        if (class_exists(CommonMarkConverter::class)) {
            $converter = new CommonMarkConverter(['html_input' => 'allow', 'allow_unsafe_links' => false]);

            return $converter->convert($md)->getContent();
        }

        return '<p>'.nl2br(e($md)).'</p>';
    }

    /** @param  list<string>  $mediaRefs */
    private function transformMedia(mixed $value, array &$mediaRefs): ?string
    {
        if (is_string($value) && $value !== '') {
            $mediaRefs[] = $value;

            return $value;
        }

        if (is_array($value)) {
            $url = $value['url'] ?? $value['src'] ?? $value['href'] ?? $value['id'] ?? null;
            if ($url !== null) {
                $ref = (string) $url;
                $mediaRefs[] = $ref;

                return $ref;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $taxonomyRefs
     * @return list<string>|string
     */
    private function transformRelation(mixed $value, array &$taxonomyRefs): array|string
    {
        if (is_array($value)) {
            $ids = [];
            foreach ($value as $item) {
                $id = is_array($item) ? (string) ($item['id'] ?? $item['slug'] ?? '') : (string) $item;
                if ($id !== '') {
                    $ids[] = $id;
                    $taxonomyRefs[] = $id;
                }
            }

            return $ids;
        }

        $ref = (string) $value;
        if ($ref !== '') {
            $taxonomyRefs[] = $ref;
        }

        return $ref;
    }

    private function transformNumber(mixed $value): int|float
    {
        if (is_numeric($value)) {
            return str_contains((string) $value, '.') ? (float) $value : (int) $value;
        }

        return 0;
    }

    private function transformBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function transformDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            Log::warning('ContentTransformer: unparseable date', ['value' => $value]);

            return (string) $value;
        }
    }

    /** @return array<string, mixed>|string */
    private function transformJson(mixed $value): array|string
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : $value;
        }

        return (string) $value;
    }

    private function transformString(mixed $value): string
    {
        return is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
    }

    /** @param  array<int|string, mixed>  $blocks */
    private function blocksToHtml(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if (is_string($block)) {
                $html .= $block;

                continue;
            }

            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? $block['nodeType'] ?? 'paragraph';
            $content = $block['content'] ?? $block['value'] ?? $block['text'] ?? '';
            $rendered = is_string($content) ? $content : '';

            $html .= match ($type) {
                'heading-1', 'heading_1', 'h1' => "<h1>{$rendered}</h1>",
                'heading-2', 'heading_2', 'h2' => "<h2>{$rendered}</h2>",
                'heading-3', 'heading_3', 'h3' => "<h3>{$rendered}</h3>",
                'blockquote', 'quote' => "<blockquote>{$rendered}</blockquote>",
                'code', 'code-block' => "<pre><code>{$rendered}</code></pre>",
                'image', 'embedded-asset-block' => $this->blockImageToHtml($block),
                default => "<p>{$rendered}</p>",
            };
        }

        return $html;
    }

    /** @param  array<string, mixed>  $block */
    private function blockImageToHtml(array $block): string
    {
        $url = $block['url'] ?? $block['src'] ?? '';
        $alt = $block['alt'] ?? $block['title'] ?? '';

        return "<img src=\"{$url}\" alt=\"{$alt}\" />";
    }

    /** @param  list<string>  $mediaRefs */
    private function extractMediaFromHtml(string $html, array &$mediaRefs): string
    {
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $mediaRefs[] = $src;
            }
        }

        return $html;
    }
}
