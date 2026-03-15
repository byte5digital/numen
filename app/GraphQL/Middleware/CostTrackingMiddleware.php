<?php

declare(strict_types=1);

namespace App\GraphQL\Middleware;

use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Field middleware that tracks query cost/complexity per request.
 *
 * Records query hash, execution time, and user_id for analytics
 * and monitoring purposes.
 */
final class CostTrackingMiddleware extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Tracks query cost and execution time for analytics.
"""
directive @costTracking on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(
            fn (callable $resolver): \Closure => function (
                mixed $root,
                array $args,
                GraphQLContext $context,
                ResolveInfo $resolveInfo,
            ) use ($resolver): mixed {
                $startTime = microtime(true);

                $result = $resolver($root, $args, $context, $resolveInfo);

                $executionTimeMs = (int) round((microtime(true) - $startTime) * 1000);

                $request = request();
                $rawQuery = (string) ($request->input('query', ''));
                $queryHash = $rawQuery !== '' ? hash('sha256', $rawQuery) : 'unknown';

                /** @var \Illuminate\Contracts\Auth\Authenticatable|null $user */
                $user = $context->user();
                $userId = $user?->getAuthIdentifier();

                // Estimate complexity score from requested subfields count
                $complexityScore = count($resolveInfo->getFieldSelection(1));

                Log::debug('graphql.cost_tracking', [
                    'field' => $resolveInfo->parentType->name.'.'.$resolveInfo->fieldName,
                    'query_hash' => $queryHash,
                    'complexity_score' => $complexityScore,
                    'execution_time_ms' => $executionTimeMs,
                    'user_id' => $userId,
                ]);

                return $result;
            }
        );
    }
}
