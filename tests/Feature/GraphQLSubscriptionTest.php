<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Basic subscription schema tests.
 *
 * Full subscription testing requires a WebSocket server (Pusher/Laravel Reverb).
 * These tests verify that subscription types are correctly defined in the schema.
 */
class GraphQLSubscriptionTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_subscription_types_are_defined_in_schema(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                __schema {
                    subscriptionType {
                        name
                        fields {
                            name
                        }
                    }
                }
            }
        ');

        $response->assertJsonPath('data.__schema.subscriptionType.name', 'Subscription');

        $fields = collect($response->json('data.__schema.subscriptionType.fields'))
            ->pluck('name');

        $this->assertTrue($fields->contains('contentPublished'), 'contentPublished subscription is missing');
        $this->assertTrue($fields->contains('contentUpdated'), 'contentUpdated subscription is missing');
        $this->assertTrue($fields->contains('pipelineRunUpdated'), 'pipelineRunUpdated subscription is missing');
        $this->assertTrue($fields->contains('pipelineRunCompleted'), 'pipelineRunCompleted subscription is missing');
    }

    public function test_content_published_subscription_exists(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                __type(name: "Subscription") {
                    fields {
                        name
                        args {
                            name
                            type {
                                name
                                kind
                            }
                        }
                    }
                }
            }
        ');

        $fields = collect($response->json('data.__type.fields'));
        $contentPublished = $fields->firstWhere('name', 'contentPublished');

        $this->assertNotNull($contentPublished, 'contentPublished field not found in Subscription type');

        $argNames = collect($contentPublished['args'])->pluck('name');
        $this->assertTrue($argNames->contains('spaceId'), 'contentPublished should accept spaceId argument');
    }
}
