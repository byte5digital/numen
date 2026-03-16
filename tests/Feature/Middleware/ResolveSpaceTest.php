<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\ResolveSpace;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class ResolveSpaceTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(?string $spaceIdHeader = null, ?string $sessionSpaceId = null): Request
    {
        $request = Request::create('/test');

        if ($spaceIdHeader) {
            $request->headers->set('X-Space-Id', $spaceIdHeader);
        }

        if ($sessionSpaceId) {
            $request->setLaravelSession(session());
            session(['current_space_id' => $sessionSpaceId]);
        }

        return $request;
    }

    public function test_resolves_from_x_space_id_header(): void
    {
        $space = Space::factory()->create();
        $request = $this->makeRequest($space->id);

        $middleware = new ResolveSpace;
        $middleware->handle($request, fn ($req) => new Response('ok'));

        $this->assertSame($space->id, app('current_space')->id);
    }

    public function test_resolves_from_session(): void
    {
        $space = Space::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->withSession(['current_space_id' => $space->id])
            ->get('/admin');

        // After request, current_space_id should be in session
        $this->assertEquals($space->id, session('current_space_id'));
    }

    public function test_fallback_to_first_space(): void
    {
        $first = Space::factory()->create(['created_at' => now()->subHour()]);
        $second = Space::factory()->create(['created_at' => now()]);

        $request = Request::create('/test');
        $request->setLaravelSession(session());

        $middleware = new ResolveSpace;
        $middleware->handle($request, fn ($req) => new Response('ok'));

        $this->assertSame($first->id, app('current_space')->id);
    }

    public function test_returns_503_when_no_spaces(): void
    {
        Space::query()->delete();

        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(503);
    }
}
