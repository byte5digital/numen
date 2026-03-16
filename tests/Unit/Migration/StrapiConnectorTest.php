<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Services\Migration\Connectors\StrapiConnector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StrapiConnectorTest extends TestCase
{
    private StrapiConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connector = new StrapiConnector(
            url: 'https://strapi.example.com',
            token: 'test-bearer-token',
        );
    }

    public function test_test_connection_returns_true_on_success(): void
    {
        Http::fake([
            '*/api/content-types' => Http::response(['data' => []], 200),
        ]);

        $this->assertTrue($this->connector->testConnection());
    }

    public function test_test_connection_returns_false_on_failure(): void
    {
        Http::fake([
            '*' => Http::response([], 401),
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

    public function test_get_content_types_returns_data(): void
    {
        $types = ['data' => [['uid' => 'api::article.article', 'apiID' => 'article']]];

        Http::fake([
            '*/api/content-types' => Http::response($types, 200),
        ]);

        $result = $this->connector->getContentTypes();

        $this->assertSame($types, $result);
    }

    public function test_get_content_types_returns_empty_on_failure(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $this->assertSame([], $this->connector->getContentTypes());
    }

    public function test_get_content_items_returns_data_array(): void
    {
        $items = [
            'data' => [
                ['id' => 1, 'attributes' => ['title' => 'Article 1']],
                ['id' => 2, 'attributes' => ['title' => 'Article 2']],
            ],
            'meta' => ['pagination' => ['total' => 2, 'page' => 1, 'pageSize' => 25]],
        ];

        Http::fake([
            '*/api/articles*' => Http::response($items, 200),
        ]);

        $result = $this->connector->getContentItems('articles', 1, 25);

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
    }

    public function test_get_content_items_sends_pagination_params(): void
    {
        Http::fake([
            '*/api/articles*' => Http::response(['data' => []], 200),
        ]);

        $this->connector->getContentItems('articles', 2, 10);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['pagination[page]'] ?? null) == 2
                && ($data['pagination[pageSize]'] ?? null) == 10;
        });
    }

    public function test_get_total_count_reads_meta(): void
    {
        Http::fake([
            '*/api/articles*' => Http::response([
                'data' => [],
                'meta' => ['pagination' => ['total' => 99]],
            ], 200),
        ]);

        $count = $this->connector->getTotalCount('articles');

        $this->assertSame(99, $count);
    }

    public function test_get_total_count_returns_zero_on_failure(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $this->assertSame(0, $this->connector->getTotalCount('articles'));
    }

    public function test_uses_bearer_auth(): void
    {
        Http::fake([
            '*/api/content-types' => Http::response([], 200),
        ]);

        $this->connector->testConnection();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization')
                && str_starts_with($request->header('Authorization')[0], 'Bearer ');
        });
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

        $this->assertSame([], $this->connector->getContentTypes());
        $this->assertSame([], $this->connector->getContentItems('articles', 1, 10));
        $this->assertSame(0, $this->connector->getTotalCount('articles'));
    }
}
