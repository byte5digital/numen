<?php

declare(strict_types=1);

namespace App\GraphQL\Complexity;

/**
 * Custom complexity resolver for paginated fields.
 *
 * Calculates complexity as childComplexity * first (pagination count).
 * This prevents abuse via deeply nested paginated queries like:
 *   { contents(first: 1000) { versions(first: 100) { ... } } }
 */
final class PaginatedComplexity
{
    /**
     * Calculate complexity for a paginated field.
     *
     * @param  int  $childComplexity  The complexity of the child selection set.
     * @param  array<string, mixed>  $args  The field arguments.
     */
    public function __invoke(int $childComplexity, array $args): int
    {
        $first = (int) ($args['first'] ?? 20);

        // Clamp to a safe maximum to avoid integer overflow in complexity calculations
        $first = min($first, 1000);

        return $childComplexity * $first;
    }
}
