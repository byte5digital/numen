<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Services\Migration\Connectors\WordPressConnector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressConnectorTest extends TestCase
{
    private WordPressConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connector = new WordPressConnector(
            url: 'https://example.com',
            username: 'admin',
            password: 'app-password',
        );
    }

    public function test_test_connection_returns_true_on_success(): void
    {
        Http::fake([
            '*/wp-json/wp/v2/' => Http::response(['namespaces' => ['wp/v2']], 200),
        ]);

        $this->assertTrue($this->connector->testConnection());
    }

    public function test_test_connection_returns_false_on_failure(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
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

    public function test_detect_version_extracts_from_generator(): void
    {
        Http::fake([
            '*/wp-json/wp/v2/' => Http::response([
                'generator' => 'https://wordpress.org/?v=6.4.2',
            ], 200),
        ]);

        $version = $this->connector->detectVersion();

        $this->assertSame('6.4.2', $version);
    }

    public function test_detect_version_returns_null_when_missing(): void
    {
        Http::fake([
            '*/wp-json/wp/v2/' => Http::response(['name' => 'Site'], 200),
        ]);

        $version = $this->connector->detectVersion();

        $this->assertNull($version);
    }

    public function test_get_content_types_returns_array(): void
    {
        $typesData = [
            'post' => ['slug' => 'post', 'name' => 'Posts'],
            'page' => ['slug' => 'page', 'name' => 'Pages'],
        ];

        Http::fake([
            '*/wp-json/wp/v2/types' => Http::response($typesData, 200),
        ]);

        $types = $this->connector->getContentTypes();

        $this->assertSame($typesData, $types);
    }

    public function test_get_content_types_returns_empty_on_failure(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $this->assertSame([], $this->connector->getContentTypes());
    }

    public function test_get_content_items_with_pagination(): void
    {
        $posts = [
            ['id' => 1, 'title' => ['rendered' => 'Post 1']],
            ['id' => 2, 'title' => ['rendered' => 'Post 2']],
        ];

        Http::fake([
            '*/wp-json/wp/v2/posts*' => Http::response($posts, 200),
        ]);

        $items = $this->connector->getContentItems('posts', 1, 10);

        $this->assertCount(2, $items);
        $this->assertSame(1, $items[0]['id']);
    }

    public function test_get_content_items_with_cursor(): void
    {
        Http::fake([
            '*/wp-json/wp/v2/posts*' => Http::response([], 200),
        ]);

        $items = $this->connector->getContentItems('posts', 1, 10, '20');

        $this->assertIsArray($items);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['offset']) && (int) $data['offset'] === 20 && ! isset($data['page']);
        });
    }

    public function test_get_total_count_reads_x_wp_total_header(): void
    {
        Http::fake([
            '*/wp-json/wp/v2/posts*' => Http::response([], 200, [
                'X-WP-Total' => '42',
            ]),
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

    public function test_get_media_items_returns_array(): void
    {
        $media = [
            ['id' => 10, 'media_type' => 'image'],
            ['id' => 11, 'media_type' => 'image'],
        ];

        Http::fake([
            '*/wp-json/wp/v2/media*' => Http::response($media, 200),
        ]);

        $items = $this->connector->getMediaItems(1, 20);

        $this->assertCount(2, $items);
    }

    public function test_get_taxonomies_returns_array(): void
    {
        $taxonomies = [
            'category' => ['slug' => 'category', 'name' => 'Categories'],
            'post_tag' => ['slug' => 'post_tag', 'name' => 'Tags'],
        ];

        Http::fake([
            '*/wp-json/wp/v2/taxonomies' => Http::response($taxonomies, 200),
        ]);

        $result = $this->connector->getTaxonomies();

        $this->assertSame($taxonomies, $result);
    }

    public function test_get_users_returns_array(): void
    {
        $users = [
            ['id' => 1, 'name' => 'Admin'],
        ];

        Http::fake([
            '*/wp-json/wp/v2/users*' => Http::response($users, 200),
        ]);

        $result = $this->connector->getUsers();

        $this->assertCount(1, $result);
        $this->assertSame('Admin', $result[0]['name']);
    }

    public function test_supports_graphql_returns_true_when_wpgraphql_active(): void
    {
        Http::fake([
            '*/graphql' => Http::response([
                'data' => ['__typename' => 'RootQuery'],
            ], 200),
        ]);

        $this->assertTrue($this->connector->supportsGraphQL());
    }

    public function test_supports_graphql_returns_false_when_not_available(): void
    {
        Http::fake([
            '*/graphql' => Http::response([], 404),
        ]);

        $this->assertFalse($this->connector->supportsGraphQL());
    }

    public function test_uses_basic_auth_when_credentials_provided(): void
    {
        Http::fake([
            '*/wp-json/wp/v2/types' => Http::response([], 200),
        ]);

        $this->connector->getContentTypes();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization') &&
                str_starts_with($request->header('Authorization')[0], 'Basic ');
        });
    }

    public function test_no_auth_when_no_credentials(): void
    {
        $connector = new WordPressConnector(url: 'https://example.com');

        Http::fake([
            '*/wp-json/wp/v2/types' => Http::response([], 200),
        ]);

        $connector->getContentTypes();

        Http::assertSent(function ($request) {
            return ! $request->hasHeader('Authorization');
        });
    }

    public function test_returns_empty_arrays_on_connection_exception(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Timeout');
            },
        ]);

        $this->assertSame([], $this->connector->getContentTypes());
        $this->assertSame([], $this->connector->getContentItems('posts', 1, 10));
        $this->assertSame([], $this->connector->getMediaItems(1, 10));
        $this->assertSame([], $this->connector->getTaxonomies());
        $this->assertSame([], $this->connector->getUsers());
        $this->assertSame(0, $this->connector->getTotalCount('posts'));
    }
}
