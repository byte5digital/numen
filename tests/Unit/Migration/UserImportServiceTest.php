<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Models\Migration\MigrationSession;
use App\Models\User;
use App\Services\Migration\CmsConnectorFactory;
use App\Services\Migration\Connectors\CmsConnectorInterface;
use App\Services\Migration\UserImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class UserImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserImportService $service;

    private CmsConnectorFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = Mockery::mock(CmsConnectorFactory::class);
        $this->service = new UserImportService($this->factory);
    }

    private function createSession(): MigrationSession
    {
        return MigrationSession::factory()->create([
            'source_cms' => 'wordpress',
            'source_url' => 'https://example.com',
            'status' => 'running',
        ]);
    }

    public function test_imports_users_and_creates_mapping(): void
    {
        $session = $this->createSession();

        $connector = Mockery::mock(CmsConnectorInterface::class);
        $connector->shouldReceive('getUsers')->andReturn([
            ['id' => '10', 'email' => 'alice@example.com', 'name' => 'Alice', 'role' => 'editor'],
            ['id' => '20', 'email' => 'bob@example.com', 'name' => 'Bob', 'role' => 'administrator'],
        ]);

        $this->factory->shouldReceive('make')->andReturn($connector);

        $mapping = $this->service->importUsers($session);

        $this->assertCount(2, $mapping);
        $this->assertTrue($mapping->has('10'));
        $this->assertTrue($mapping->has('20'));

        $alice = User::where('email', 'alice@example.com')->first();
        $this->assertNotNull($alice);
        $this->assertSame('Alice', $alice->name);
        $this->assertSame('editor', $alice->role);

        $bob = User::where('email', 'bob@example.com')->first();
        $this->assertNotNull($bob);
        $this->assertSame('admin', $bob->role);
    }

    public function test_matches_existing_users_by_email(): void
    {
        $session = $this->createSession();

        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Existing User',
        ]);

        $connector = Mockery::mock(CmsConnectorInterface::class);
        $connector->shouldReceive('getUsers')->andReturn([
            ['id' => '99', 'email' => 'existing@example.com', 'name' => 'Different Name', 'role' => 'author'],
        ]);

        $this->factory->shouldReceive('make')->andReturn($connector);

        $mapping = $this->service->importUsers($session);

        $this->assertSame($existingUser->id, $mapping->get('99'));
        // Should not create a new user
        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
    }

    public function test_creates_users_with_placeholder_email_when_empty(): void
    {
        $session = $this->createSession();

        $connector = Mockery::mock(CmsConnectorInterface::class);
        $connector->shouldReceive('getUsers')->andReturn([
            ['id' => '5', 'email' => '', 'name' => 'No Email User', 'role' => 'author'],
        ]);

        $this->factory->shouldReceive('make')->andReturn($connector);

        $mapping = $this->service->importUsers($session);

        $this->assertTrue($mapping->has('5'));

        $user = User::find($mapping->get('5'));
        $this->assertNotNull($user);
        $this->assertStringStartsWith('migrated-no-email-user-', $user->email);
        $this->assertStringEndsWith('@numen.local', $user->email);
    }

    public function test_maps_roles_correctly(): void
    {
        $this->assertSame('admin', $this->service->mapRole('administrator'));
        $this->assertSame('admin', $this->service->mapRole('Owner'));
        $this->assertSame('editor', $this->service->mapRole('author'));
        $this->assertSame('editor', $this->service->mapRole('contributor'));
        $this->assertSame('viewer', $this->service->mapRole('subscriber'));
        $this->assertSame('editor', $this->service->mapRole('unknown_role'));
    }

    public function test_skips_users_without_source_id(): void
    {
        $session = $this->createSession();

        $connector = Mockery::mock(CmsConnectorInterface::class);
        $connector->shouldReceive('getUsers')->andReturn([
            ['email' => 'noid@example.com', 'name' => 'No ID'],
        ]);

        $this->factory->shouldReceive('make')->andReturn($connector);

        $mapping = $this->service->importUsers($session);

        $this->assertCount(0, $mapping);
    }
}
