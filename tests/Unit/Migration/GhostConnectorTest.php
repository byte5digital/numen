<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Services\Migration\Connectors\GhostConnector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GhostConnectorTest extends TestCase
{
    private GhostConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connector = new GhostConnector(
            url: 'https://ghost.example.com',
            apiKey: 'test-content-api-key',
        );
    }

    public function test_test_connection_returns_true_on_success(): void
    {
        Http::fake([
            '*/ghost/api/content/posts/*' => Http::response([
                'posts' => [],
                'meta' => ['pagination' => ['total' => 0]],
            ], 200),
        ]);

        $this->assertTrue($this->connector->testConnection());
    }

    public function test_test_connection_returns_false_on_failure(): void
    {
        Http::fake([
            '*' => Http::response(['errors' => [['type' => 'UnauthorizedError']]], 401),
        ]);

        $this->assertFalse($this->connector->testConnection());
    }

    public function test_test_connection_returns_false_on_connection_exception(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $this->assertFalse($this->connector->testConnection());
    }

    public function test_get_content_types_returns_static_list(): void
    {
        $types = $this->connector->getContentTypes();

        $this->assertArrayHasKey('posts', $types);
        $this->assertArrayHasKey('pages', $types);
        $this->assertArrayHasKey('tags', $types);
        $this->assertArrayHasKey('authors', $types);
    }

    public function test_get_content_items_returns_posts(): void
    {
        $posts = [
            'posts' => [
                ['id' => 'abc123', 'title' => 'Hello World', 'slug' => 'hello-world'],
                ['id' => 'def456', 'title' => 'Post Two', 'slug' => 'post-two'],
            ],
            'meta' => ['pagination' => ['total' => 2]],
        ];

        Http::fake([
            '*/ghost/api/content/posts/*' => Http::response($posts, 200),
        ]);

        $result = $this->connector->getContentItems('posts', 1, 10);

        $this->assertCount(2, $result);
        $this->assertSame('abc123', $result[0]['id']);
    }

    public function test_get_content_items_sends_api_key_and_pagination(): void
    {
        Http::fake([
            '*/ghost/api/content/posts/*' => Http::response(['posts' => []], 200),
        ]);

        $this->connector->getContentItems('posts', 2, 15);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['key'] ?? null) === 'test-content-api-key'
                && ($data['page'] ?? null) == 2
                && ($data['limit'] ?? null) == 15;
        });
    }

    public function test_get_content_items_returns_empty_on_failure(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $this->assertSame([], $this->connector->getContentItems('posts', 1, 10));
    }

    public function test_get_total_count_reads_pagination_meta(): void
    {
        Http::fake([
            '*/ghost/api/content/posts/*' => Http::response([
                'posts' => [],
                'meta' => ['pagination' => ['total' => 42]],
            ], 200),
        ]);

        $count = $this->connector->getTotalCount('posts');

        $this->assertSame(42, $count);
    }

    public function test_get_total_count_returns_zero_on_failure(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $this->assertSame(0, $this->connector->getTotalCount('posts'));
    }

    public function test_get_taxonomies_returns_tags(): void
    {
        Http::fake([
            '*/ghost/api/content/tags/*' => Http::response([
                'tags' => [
                    ['id' => 't1', 'name' => 'Laravel'],
                    ['id' => 't2', 'name' => 'PHP'],
                ],
            ], 200),
        ]);

        $tags = $this->connector->getTaxonomies();

        $this->assertCount(2, $tags);
    }

    public function test_supports_graphql_returns_false(): void
    {
        $this->assertFalse($this->connector->supportsGraphQL());
    }

    public function test_returns_empty_on_connection_exception(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Timeout');
            },
        ]);

        $this->assertFalse($this->connector->testConnection());
        $this->assertSame([], $this->connector->getContentItems('posts', 1, 10));
        $this->assertSame(0, $this->connector->getTotalCount('posts'));
    }
}
