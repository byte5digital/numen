<?php

namespace App\Services\Search;

use App\Services\Search\Results\SearchResult;
use App\Services\Search\Results\SearchResultCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tier 2: Semantic search via pgvector cosine similarity.
 * Only available when PostgreSQL + pgvector extension is enabled.
 */
class SemanticSearchDriver
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
    ) {}

    public function search(SearchQuery $query): SearchResultCollection
    {
        try {
            $embedding = $this->embeddings->embed($query->query);

            if (empty($embedding)) {
                return SearchResultCollection::empty('semantic');
            }

            $maxSources = (int) config('numen.search.rag_max_sources', 5) * 4;
            $limit = min($query->perPage * 2, $maxSources);

            $vectorStr = '['.implode(',', $embedding).']';

            $sql = <<<'SQL'
                SELECT
                    ce.content_id,
                    ce.chunk_text,
                    ce.chunk_type,
                    1 - (ce.embedding <=> :vector::vector) AS similarity,
                    cv.title,
                    cv.excerpt,
                    c.slug,
                    c.locale,
                    c.published_at,
                    ct.slug AS content_type_slug
                FROM content_embeddings ce
                JOIN contents c ON c.id = ce.content_id
                    AND c.status = 'published'
                    AND c.space_id = :space_id
                JOIN content_versions cv ON cv.id = ce.content_version_id
                JOIN content_types ct ON ct.id = c.content_type_id
                WHERE ce.space_id = :space_id2
                SQL;

            $bindings = [
                'vector' => $vectorStr,
                'space_id' => $query->spaceId,
                'space_id2' => $query->spaceId,
            ];

            if ($query->locale) {
                $sql .= ' AND ce.locale = :locale AND c.locale = :locale2';
                $bindings['locale'] = $query->locale;
                $bindings['locale2'] = $query->locale;
            }

            if ($query->contentType) {
                $sql .= ' AND ct.slug = :content_type';
                $bindings['content_type'] = $query->contentType;
            }

            $sql .= ' ORDER BY similarity DESC LIMIT :limit OFFSET :offset';
            $bindings['limit'] = $limit;
            $bindings['offset'] = ($query->page - 1) * $query->perPage;

            $rows = DB::select($sql, $bindings);

            // Deduplicate by content_id (keep best-scoring chunk per content)
            $seen = [];
            $items = [];

            foreach ($rows as $row) {
                if (isset($seen[$row->content_id])) {
                    continue;
                }
                $seen[$row->content_id] = true;

                $items[] = new SearchResult(
                    contentId: $row->content_id,
                    title: $row->title ?? '',
                    excerpt: $row->excerpt ?? $row->chunk_text ?? '',
                    url: "/content/{$row->slug}",
                    contentType: $row->content_type_slug ?? '',
                    score: (float) ($row->similarity ?? 0),
                    publishedAt: $row->published_at ?? '',
                    highlights: [],
                );
            }

            // Trim to requested page size
            $items = array_slice($items, 0, $query->perPage);

            return new SearchResultCollection(
                items: $items,
                total: count($items),
                page: $query->page,
                perPage: $query->perPage,
                tierUsed: 'semantic',
            );

        } catch (\Throwable $e) {
            Log::warning('SemanticSearchDriver: search failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Retrieve top chunks for RAG context.
     *
     * @param  float[]  $embedding
     * @return array<int, object>
     */
    public function retrieveChunks(array $embedding, string $spaceId, int $limit = 10, ?string $locale = null): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return [];
        }

        $vectorStr = '['.implode(',', $embedding).']';

        $sql = <<<'SQL'
            SELECT
                ce.content_id,
                ce.chunk_text,
                ce.chunk_type,
                1 - (ce.embedding <=> :vector::vector) AS similarity,
                cv.title AS content_title,
                c.slug AS content_slug,
                c.published_at
            FROM content_embeddings ce
            JOIN contents c ON c.id = ce.content_id
                AND c.status = 'published'
                AND c.space_id = :space_id
            JOIN content_versions cv ON cv.id = ce.content_version_id
            WHERE ce.space_id = :space_id2
            SQL;

        $bindings = [
            'vector' => $vectorStr,
            'space_id' => $spaceId,
            'space_id2' => $spaceId,
        ];

        if ($locale) {
            $sql .= ' AND ce.locale = :locale';
            $bindings['locale'] = $locale;
        }

        $sql .= ' ORDER BY similarity DESC LIMIT :limit';
        $bindings['limit'] = $limit;

        return DB::select($sql, $bindings);
    }
}
