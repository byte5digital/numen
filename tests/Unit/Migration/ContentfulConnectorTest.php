<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Services\Migration\Connectors\ContentfulConnector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentfulConnectorTest extends TestCase
{
    private ContentfulConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connector = new ContentfulConnector(
            spaceId: 'test-space-id',
            accessToken: 'test-cda-token',
            environment: 'master',
        );
    }

    public function test_test_connection_returns_true_on_success(): void
    {
        Http::fake([
            'cdn.contentful.com/*' => Http::response(['items' => [], 'total' => 0], 200),
        ]);

        $this->assertTrue($this->connector->testConnection());
    }

    public function test_test_connection_returns_false_on_failure(): void
    {
        Http::fake([
            '*' => Http::response(['sys' => ['type' => 'Error']], 401),
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
        $types = [
            'items' => [
                ['sys' => ['id' => 'blogPost'], 'name' => 'Blog Post'],
            ],
            'total' => 1,
        ];

        Http::fake([
            'cdn.contentful.com/*/content_types' => Http::response($types, 200),
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

    public function test_get_content_items_returns_items(): void
    {
        $entries = [
            'items' => [
                ['sys' => ['id' => 'entry1'], 'fields' => ['title' => 'Entry 1']],
            ],
            'total' => 1,
        ];

        Http::fake([
            'cdn.contentful.com/*/entries*' => Http::response($entries, 200),
        ]);

        $result = $this->connector->getContentItems('blogPost', 1, 10);

        $this->assertCount(1, $result);
        $this->assertSame('entry1', $result[0]['sys']['id']);
    }

    public function test_get_content_items_uses_offset_pagination(): void
    {
        Http::fake([
            'cdn.contentful.com/*/entries*' => Http::response(['items' => []], 200),
        ]);

        $this->connector->getContentItems('blogPost', 3, 10);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['skip'] ?? null) == 20 && ($data['limit'] ?? null) == 10;
        });
    }

    public function test_get_total_count_reads_total(): void
    {
        Http::fake([
            'cdn.contentful.com/*/entries*' => Http::response(['items' => [], 'total' => 55], 200),
        ]);

        $count = $this->connector->getTotalCount('blogPost');

        $this->assertSame(55, $count);
    }

    public function test_get_total_count_returns_zero_on_failure(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $this->assertSame(0, $this->connector->getTotalCount('blogPost'));
    }

    public function test_uses_bearer_auth(): void
    {
        Http::fake([
            'cdn.contentful.com/*' => Http::response(['items' => []], 200),
        ]);

        $this->connector->testConnection();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization')
                && str_starts_with($request->header('Authorization')[0], 'Bearer ');
        });
    }

    public function test_uses_correct_space_and_environment_in_url(): void
    {
        Http::fake([
            'cdn.contentful.com/spaces/test-space-id/environments/master/*' => Http::response(['items' => []], 200),
        ]);

        $result = $this->connector->testConnection();

        $this->assertTrue($result);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/spaces/test-space-id/environments/master/');
        });
    }

    public function test_supports_graphql_returns_true(): void
    {
        $this->assertTrue($this->connector->supportsGraphQL());
    }

    public function test_returns_empty_on_connection_exception(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Timeout');
            },
        ]);

        $this->assertSame([], $this->connector->getContentTypes());
        $this->assertSame([], $this->connector->getContentItems('blogPost', 1, 10));
        $this->assertSame(0, $this->connector->getTotalCount('blogPost'));
    }
}
