<?php

namespace App\GraphQL\Queries;

use App\Models\Content;
use App\Models\Space;

final class ContentBySlugQuery
{
    /**
     * @param  array{slug: string, locale: string, spaceSlug: string}  $args
     */
    public function __invoke(mixed $root, array $args): ?Content
    {
        $space = Space::where('slug', $args['spaceSlug'])->first();

        if (! $space) {
            return null;
        }

        /** @var Content|null */
        return Content::query()
            ->where('space_id', $space->id)
            ->where('slug', $args['slug'])
            ->where('locale', $args['locale'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->first();
    }
}
