<?php

namespace App\GraphQL\Queries;

use App\Models\Content;
use App\Models\Space;
use Illuminate\Pagination\LengthAwarePaginator;

final class SpaceContentsQuery
{
    /**
     * @param  array{status?: string, locale?: string, first?: int, page?: int}  $args
     * @return array{data: \Illuminate\Database\Eloquent\Collection<int, Content>, paginatorInfo: array<string, mixed>}
     */
    public function __invoke(Space $root, array $args): array
    {
        $query = Content::query()->where('space_id', $root->id);

        if (! empty($args['status'])) {
            $query->where('status', $args['status']);
        }

        if (! empty($args['locale'])) {
            $query->where('locale', $args['locale']);
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
}
