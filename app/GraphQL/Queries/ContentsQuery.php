<?php

namespace App\GraphQL\Queries;

use App\Models\Content;
use App\Models\Space;
use Illuminate\Pagination\LengthAwarePaginator;

final class ContentsQuery
{
    /**
     * @param  array{spaceSlug: string, locale?: string, contentType?: string, status?: string, first?: int, page?: int}  $args
     * @return array{data: \Illuminate\Database\Eloquent\Collection<int, Content>, paginatorInfo: array<string, mixed>}
     */
    public function __invoke(mixed $root, array $args): array
    {
        $space = Space::where('slug', $args['spaceSlug'])->first();

        if (! $space) {
            return [
                'data' => collect(),
                'paginatorInfo' => $this->emptyPaginatorInfo($args['first'] ?? 20),
            ];
        }

        $query = Content::query()
            ->where('space_id', $space->id)
            ->where('status', $args['status'] ?? 'published');

        if (! empty($args['locale'])) {
            $query->where('locale', $args['locale']);
        }

        if (! empty($args['contentType'])) {
            $query->whereHas('contentType', fn ($q) => $q->where('slug', $args['contentType']));
        }

        $perPage = $args['first'] ?? 20;
        $page = $args['page'] ?? 1;

        /** @var LengthAwarePaginator<Content> $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginator->getCollection(),
            'paginatorInfo' => [
                'count' => $paginator->count(),
                'currentPage' => $paginator->currentPage(),
                'firstItem' => $paginator->firstItem(),
                'hasMorePages' => $paginator->hasMorePages(),
                'lastItem' => $paginator->lastItem(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPaginatorInfo(int $perPage): array
    {
        return [
            'count' => 0,
            'currentPage' => 1,
            'firstItem' => null,
            'hasMorePages' => false,
            'lastItem' => null,
            'lastPage' => 1,
            'perPage' => $perPage,
            'total' => 0,
        ];
    }
}
