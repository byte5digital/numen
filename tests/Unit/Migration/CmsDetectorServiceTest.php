<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Services\Migration\CmsDetectorService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CmsDetectorServiceTest extends TestCase
{
    private CmsDetectorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CmsDetectorService;
    }

    public function test_detects_wordpress_from_wp_json(): void
    {
        Http::fake([
            '*/wp-json/wp/v2' => Http::response([
                'name' => 'My WordPress Site',
                'generator' => 'https://wordpress.org/?v=6.4.2',
            ], 200),
        ]);

        $result = $this->service->detect('https://example.com');

        $this->assertSame('wordpress', $result['cms']);
        $this->assertSame('6.4.2', $result['version']);
        $this->assertGreaterThanOrEqual(0.9, $result['confidence']);
    }

    public function test_detects_wordpress_without_version(): void
    {
        Http::fake([
            '*/wp-json/wp/v2' => Http::response([
                'name' => 'My WordPress Site',
            ], 200),
        ]);

        $result = $this->service->detect('https://example.com');

        $this->assertSame('wordpress', $result['cms']);
        $this->assertNull($result['version']);
        $this->assertGreaterThanOrEqual(0.9, $result['confidence']);
    }

    public function test_detects_directus_from_server_info(): void
    {
        Http::fake([
            '*/wp-json/wp/v2' => Http::response([], 404),
            '*/wp-login.php' => Http::response('', 404),
            '*/api' => Http::response([], 404),
            '*/_health' => Http::response('', 404),
            '*contentful*' => Http::response([], 404),
            '*/ghost/*' => Http::response([], 404),
            '*/server/info' => Http::response([
                'data' => ['version' => '10.8.0'],
            ], 200),
            '*/server/ping' => Http::response('pong', 200),
            '*/admin' => Http::response('', 404),
        ]);

        $result = $this->service->detect('https://directus.example.com');

        $this->assertSame('directus', $result['cms']);
        $this->assertSame('10.8.0', $result['version']);
        $this->assertGreaterThanOrEqual(0.9, $result['confidence']);
    }

    public function test_returns_unknown_when_no_cms_detected(): void
    {
        Http::fake([
            '*' => Http::response([], 404),
        ]);

        $result = $this->service->detect('https://unknown.example.com');

        $this->assertSame('unknown', $result['cms']);
        $this->assertNull($result['version']);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function test_detects_ghost_from_content_api(): void
    {
        Http::fake([
            '*/wp-json/wp/v2' => Http::response([], 404),
            '*/wp-login.php' => Http::response('', 404),
            '*/api' => Http::response([], 404),
            '*/_health' => Http::response('', 404),
            '*contentful*' => Http::response([], 404),
            '*/ghost/api/content/*' => Http::response([
                'posts' => [],
            ], 200),
            '*/ghost/api/admin*' => Http::response([], 200),
            '*/server/info' => Http::response([], 404),
            '*/server/ping' => Http::response('', 404),
            '*/admin' => Http::response('', 404),
        ]);

        $result = $this->service->detect('https://ghost.example.com');

        $this->assertSame('ghost', $result['cms']);
        $this->assertGreaterThanOrEqual(0.8, $result['confidence']);
    }

    public function test_handles_connection_exceptions_gracefully(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $result = $this->service->detect('https://unreachable.example.com');

        $this->assertArrayHasKey('cms', $result);
        $this->assertArrayHasKey('version', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function test_detects_contentful_from_domain(): void
    {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $result = $this->service->detect('https://cdn.contentful.com/spaces/abc123');

        $this->assertSame('contentful', $result['cms']);
        $this->assertGreaterThanOrEqual(0.9, $result['confidence']);
    }

    public function test_result_has_required_keys(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $result = $this->service->detect('https://example.com');

        $this->assertArrayHasKey('cms', $result);
        $this->assertArrayHasKey('version', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertIsFloat($result['confidence']);
    }
}
