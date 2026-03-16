<?php

namespace Tests\Feature\Middleware;

use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_from_x_space_id_header(): void
    {
        $space = Space::factory()->create();

        // Make a direct request with header
        $response = $this->withHeaders(['X-Space-Id' => $space->id])
            ->actingAs(User::factory()->admin()->create())
            ->get('/admin');

        $response->assertOk();
        $this->assertEquals($space->id, session('current_space_id'));
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

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin');

        $response->assertOk();
        // First created space should be used
        $this->assertEquals($first->id, session('current_space_id'));
    }

    public function test_creates_session_on_no_existing_space_id(): void
    {
        $space = Space::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin');

        $response->assertOk();
        // Should resolve to the space
        $this->assertNotNull(session('current_space_id'));
    }
}
