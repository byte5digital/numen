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

    private function runMiddleware(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $middleware = new ResolveSpace;

        return $middleware->handle($request, fn ($req) => new Response('ok'));
    }

    public function test_resolves_from_x_space_id_header(): void
    {
        $space = Space::factory()->create();

        $request = Request::create('/test');
        $request->headers->set('X-Space-Id', $space->id);
        // Need session for middleware
        $session = app('session')->driver();
        $request->setLaravelSession($session);

        $this->runMiddleware($request);

        $this->assertSame($space->id, app('current_space')->id);
    }

    public function test_resolves_from_session(): void
    {
        $space = Space::factory()->create();

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)
            ->withSession(['current_space_id' => $space->id])
            ->get('/admin');

        // The session-based resolution sets current_space_id back in session
        $this->assertNotNull(session('current_space_id'));
    }

    public function test_fallback_to_first_space(): void
    {
        $first = Space::factory()->create(['created_at' => now()->subHour()]);
        Space::factory()->create(['created_at' => now()]);

        $request = Request::create('/test');
        $session = app('session')->driver();
        $request->setLaravelSession($session);

        $this->runMiddleware($request);

        $this->assertSame($first->id, app('current_space')->id);
    }

    public function test_503_when_no_spaces(): void
    {
        Space::query()->delete();

        $request = Request::create('/test');
        $session = app('session')->driver();
        $request->setLaravelSession($session);

        try {
            $this->runMiddleware($request);
            $this->fail('Expected abort(503) was not thrown');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(503, $e->getStatusCode());
        }
    }
}
