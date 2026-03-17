<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Models\ContentType;
use App\Services\Migration\Connectors\CmsConnectorInterface;

/** Normalises CMS schema into a standard format. */
class SchemaInspectorService
{
    /** @var array<string, string> */
    private array $fieldTypeMap = [
        'string' => 'string', 'text' => 'string', 'short_text' => 'string',
        'symbol' => 'string', 'line' => 'string', 'email' => 'string',
        'url' => 'string', 'slug' => 'string', 'uid' => 'string', 'uuid' => 'string',
        'varchar' => 'string',
        'richtext' => 'richtext', 'rich_text' => 'richtext', 'rich-text' => 'richtext',
        'wysiwyg' => 'richtext', 'blocks' => 'richtext', 'dynamiczone' => 'richtext',
        'dynamic_zone' => 'richtext', 'longtext' => 'richtext', 'long_text' => 'richtext',
        'html' => 'html', 'raw_html' => 'html',
        'markdown' => 'markdown', 'textarea' => 'markdown',
        'number' => 'number', 'integer' => 'number', 'biginteger' => 'number',
        'decimal' => 'number', 'float' => 'number', 'int' => 'number', 'double' => 'number',
        'boolean' => 'boolean', 'bool' => 'boolean', 'checkbox' => 'boolean',
        'date' => 'date', 'datetime' => 'date', 'time' => 'date', 'timestamp' => 'date',
        'media' => 'media', 'image' => 'media', 'file' => 'media', 'asset' => 'media',
        'attachment' => 'media', 'upload' => 'media',
        'relation' => 'relation', 'reference' => 'relation', 'array' => 'relation',
        'component' => 'relation', 'link' => 'relation', 'taxonomy' => 'relation',
        'category' => 'relation', 'tag' => 'relation',
        'json' => 'json', 'jsonb' => 'json', 'object' => 'json', 'mixed' => 'json',
        'enumeration' => 'enum', 'enum' => 'enum', 'select' => 'enum',
        'choice' => 'enum', 'list' => 'enum',
    ];

    /**
     * @return array<int, array{key: string, label: string, fields: array<int, array{name: string, type: string, required: bool}>}>
     */
    public function inspectSchema(CmsConnectorInterface $connector): array
    {
        $raw = $connector->getContentTypes();
        if (empty($raw)) {
            return [];
        }

        return $this->normalise($raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<int, array{key: string, label: string, fields: array<int, array{name: string, type: string, required: bool}>}>
     */
    public function normalise(array $raw): array
    {
        if (isset($raw['data']) && is_array($raw['data'])) {
            return $this->normaliseStrapi($raw['data']);
        }
        if (isset($raw['items']) && is_array($raw['items'])) {
            return $this->normaliseContentful($raw['items']);
        }
        if (isset($raw['collections']) && is_array($raw['collections'])) {
            return $this->normalisePayload($raw['collections']);
        }
        if (isset($raw[0]['collection'])) {
            return $this->normaliseDirectus($raw);
        }
        if ($this->looksLikeGhostTypes($raw)) {
            return $this->normaliseGhost($raw);
        }
        if ($this->looksLikeWordPressTypes($raw)) {
            return $this->normaliseWordPress($raw);
        }

        return [];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function normaliseStrapi(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $key = $item['apiID'] ?? (isset($item['uid']) ? (string) $item['uid'] : null);
            if ($key === null) {
                continue;
            }
            $schema = $item['schema'] ?? [];
            $label = $schema['displayName'] ?? $schema['name'] ?? ucfirst((string) $key);
            $result[] = [
                'key' => (string) $key,
                'label' => (string) $label,
                'fields' => $this->normaliseFields($schema['attributes'] ?? [], 'strapi'),
            ];
        }

        return $result;
    }

    /** @param array<string, mixed> $raw */
    private function normaliseWordPress(array $raw): array
    {
        $result = [];
        foreach ($raw as $slug => $type) {
            if (! is_array($type)) {
                continue;
            }
            $result[] = [
                'key' => (string) $slug,
                'label' => (string) ($type['name'] ?? ucfirst((string) $slug)),
                'fields' => $this->wordPressDefaultFields((string) $slug),
            ];
        }

        return $result;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function normaliseContentful(array $items): array
    {
        $result = [];
        foreach ($items as $ct) {
            if (! is_array($ct)) {
                continue;
            }
            $key = $ct['sys']['id'] ?? null;
            if ($key === null) {
                continue;
            }
            $result[] = [
                'key' => (string) $key,
                'label' => (string) ($ct['name'] ?? ucfirst((string) $key)),
                'fields' => $this->normaliseFields($ct['fields'] ?? [], 'contentful'),
            ];
        }

        return $result;
    }

    /** @param array<int, array<string, mixed>> $collections */
    private function normaliseDirectus(array $collections): array
    {
        $result = [];
        foreach ($collections as $col) {
            if (! is_array($col)) {
                continue;
            }
            $key = $col['collection'] ?? null;
            if ($key === null) {
                continue;
            }
            $result[] = [
                'key' => (string) $key,
                'label' => (string) ($col['meta']['note'] ?? ucfirst(str_replace('_', ' ', (string) $key))),
                'fields' => $this->normaliseFields($col['fields'] ?? [], 'directus'),
            ];
        }

        return $result;
    }

    /** @param array<int, array<string, mixed>> $collections */
    private function normalisePayload(array $collections): array
    {
        $result = [];
        foreach ($collections as $col) {
            if (! is_array($col)) {
                continue;
            }
            $key = $col['slug'] ?? null;
            if ($key === null) {
                continue;
            }
            $result[] = [
                'key' => (string) $key,
                'label' => (string) ($col['labels']['singular'] ?? ucfirst((string) $key)),
                'fields' => $this->normaliseFields($col['fields'] ?? [], 'payload'),
            ];
        }

        return $result;
    }

    /** @param array<string, mixed> $raw */
    private function normaliseGhost(array $raw): array
    {
        $known = ['posts' => 'Posts', 'pages' => 'Pages'];
        $result = [];
        foreach ($raw as $key => $value) {
            if (isset($known[$key])) {
                $result[] = [
                    'key' => $key,
                    'label' => $known[$key],
                    'fields' => $this->ghostDefaultFields($key),
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $rawFields
     * @return list<array{name: string, type: string, required: bool}>
     */
    public function normaliseFields(array $rawFields, string $cms = 'generic'): array
    {
        $fields = [];
        foreach ($rawFields as $nameOrIndex => $fieldDef) {
            if (! is_array($fieldDef)) {
                continue;
            }
            $name = match ($cms) {
                'contentful' => $fieldDef['apiName'] ?? $fieldDef['id'] ?? (is_string($nameOrIndex) ? $nameOrIndex : null),
                'directus' => $fieldDef['field'] ?? (is_string($nameOrIndex) ? $nameOrIndex : null),
                default => $fieldDef['name'] ?? (is_string($nameOrIndex) ? $nameOrIndex : null),
            };
            if ($name === null) {
                continue;
            }
            $rawType = (string) ($fieldDef['type'] ?? 'string');
            $required = (bool) ($fieldDef['required'] ?? false);
            $fields[] = [
                'name' => (string) $name,
                'type' => $this->mapFieldType($rawType),
                'required' => $required,
            ];
        }

        return $fields;
    }

    public function mapFieldType(string $rawType): string
    {
        return $this->fieldTypeMap[strtolower(trim($rawType))] ?? 'string';
    }

    /** @return list<array{name: string, type: string, required: bool}> */
    private function wordPressDefaultFields(string $typeSlug): array
    {
        $base = [
            ['name' => 'id', 'type' => 'number', 'required' => true],
            ['name' => 'title', 'type' => 'string', 'required' => true],
            ['name' => 'content', 'type' => 'richtext', 'required' => false],
            ['name' => 'excerpt', 'type' => 'string', 'required' => false],
            ['name' => 'slug', 'type' => 'string', 'required' => true],
            ['name' => 'status', 'type' => 'enum', 'required' => true],
            ['name' => 'date', 'type' => 'date', 'required' => false],
            ['name' => 'modified', 'type' => 'date', 'required' => false],
            ['name' => 'author', 'type' => 'relation', 'required' => false],
            ['name' => 'featured_media', 'type' => 'media', 'required' => false],
        ];
        if (in_array($typeSlug, ['post', 'posts'], true)) {
            $base[] = ['name' => 'categories', 'type' => 'relation', 'required' => false];
            $base[] = ['name' => 'tags', 'type' => 'relation', 'required' => false];
        }

        return $base;
    }

    /** @return list<array{name: string, type: string, required: bool}> */
    private function ghostDefaultFields(string $typeSlug): array
    {
        return [
            ['name' => 'id', 'type' => 'string', 'required' => true],
            ['name' => 'title', 'type' => 'string', 'required' => true],
            ['name' => 'html', 'type' => 'html', 'required' => false],
            ['name' => 'lexical', 'type' => 'json', 'required' => false],
            ['name' => 'slug', 'type' => 'string', 'required' => true],
            ['name' => 'status', 'type' => 'enum', 'required' => true],
            ['name' => 'published_at', 'type' => 'date', 'required' => false],
            ['name' => 'feature_image', 'type' => 'media', 'required' => false],
            ['name' => 'tags', 'type' => 'relation', 'required' => false],
            ['name' => 'authors', 'type' => 'relation', 'required' => false],
            ['name' => 'excerpt', 'type' => 'string', 'required' => false],
        ];
    }

    /** @param array<string, mixed> $raw */
    private function looksLikeWordPressTypes(array $raw): bool
    {
        $first = reset($raw);

        return is_array($first) && (isset($first['slug']) || isset($first['name']));
    }

    /** @param array<string, mixed> $raw */
    private function looksLikeGhostTypes(array $raw): bool
    {
        return isset($raw['posts']) || isset($raw['pages']);
    }

    /**
     * Introspect Numen's own content types into the same normalised format.
     *
     * @return list<array{key: string, label: string, fields: list<array{name: string, type: string, required: bool}>}>
     */
    public function inspectNumenSchema(?string $spaceId = null): array
    {
        $query = ContentType::query();
        if ($spaceId !== null) {
            $query->where('space_id', $spaceId);
        }

        $contentTypes = $query->get();
        $result = [];

        foreach ($contentTypes as $ct) {
            $schema = $ct->schema ?? [];
            $fields = [];

            foreach ($schema as $fieldName => $fieldDef) {
                if (! is_array($fieldDef)) {
                    continue;
                }

                $rawType = (string) ($fieldDef['type'] ?? 'string');
                $fields[] = [
                    'name' => is_string($fieldName) ? $fieldName : (string) ($fieldDef['name'] ?? $fieldName),
                    'type' => $this->mapFieldType($rawType),
                    'required' => (bool) ($fieldDef['required'] ?? false),
                ];
            }

            $result[] = [
                'key' => $ct->slug,
                'label' => $ct->name,
                'fields' => $fields,
            ];
        }

        return $result;
    }

    /**
     * Compare source and Numen schemas to find overlaps and differences.
     *
     * @param  list<array{key: string, label: string, fields: list<array{name: string, type: string, required: bool}>}>  $source
     * @param  list<array{key: string, label: string, fields: list<array{name: string, type: string, required: bool}>}>  $numen
     * @return array{matched_types: list<array{source: string, numen: string, field_overlap: int, field_total: int}>, unmatched_source: list<string>, unmatched_numen: list<string>}
     */
    public function compareSchemas(array $source, array $numen): array
    {
        $numenByKey = [];
        foreach ($numen as $nt) {
            $numenByKey[strtolower($nt['key'])] = $nt;
        }

        $matched = [];
        $matchedNumenKeys = [];
        $unmatchedSource = [];

        foreach ($source as $st) {
            $sourceKey = strtolower($st['key']);
            $bestMatch = null;
            $bestScore = 0.0;

            foreach ($numen as $nt) {
                $numenKey = strtolower($nt['key']);
                if ($sourceKey === $numenKey) {
                    $bestMatch = $nt;
                    $bestScore = 1.0;
                    break;
                }

                similar_text($sourceKey, $numenKey, $percent);
                $score = $percent / 100;
                if ($score > $bestScore && $score >= 0.5) {
                    $bestScore = $score;
                    $bestMatch = $nt;
                }
            }

            if ($bestMatch !== null) {
                $sourceFieldNames = array_map('strtolower', array_column($st['fields'], 'name'));
                $numenFieldNames = array_map('strtolower', array_column($bestMatch['fields'], 'name'));
                $overlap = count(array_intersect($sourceFieldNames, $numenFieldNames));

                $matched[] = [
                    'source' => $st['key'],
                    'numen' => $bestMatch['key'],
                    'field_overlap' => $overlap,
                    'field_total' => count($st['fields']),
                ];
                $matchedNumenKeys[] = strtolower($bestMatch['key']);
            } else {
                $unmatchedSource[] = $st['key'];
            }
        }

        $unmatchedNumen = [];
        foreach ($numen as $nt) {
            if (! in_array(strtolower($nt['key']), $matchedNumenKeys, true)) {
                $unmatchedNumen[] = $nt['key'];
            }
        }

        return [
            'matched_types' => $matched,
            'unmatched_source' => $unmatchedSource,
            'unmatched_numen' => $unmatchedNumen,
        ];
    }
}
