<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\BudgetCheckResult;
use App\Services\Authorization\BudgetGuard;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetGuardTest extends TestCase
{
    use RefreshDatabase;

    private BudgetGuard $guard;

    private AuthorizationService $authz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->authz = app(AuthorizationService::class);
        $this->guard = app(BudgetGuard::class);
    }

    // ── Basic allow/deny ──────────────────────────────────────────────────

    public function test_viewer_cannot_generate(): void
    {
        $user = User::factory()->create();
        $viewer = Role::where('slug', 'viewer')->first();
        $space = Space::factory()->create();

        $user->roles()->attach($viewer->id, ['space_id' => null]);
        $this->authz->clearCache();

        $result = $this->guard->canGenerate($user, $space, 'claude-haiku-4-5');

        $this->assertSame(BudgetCheckResult::Denied, $result);
    }

    public function test_author_can_generate_with_haiku(): void
    {
        $user = User::factory()->create();
        $author = Role::where('slug', 'author')->first();
        $space = Space::factory()->create();

        $user->roles()->attach($author->id, ['space_id' => null]);
        $this->authz->clearCache();

        $result = $this->guard->canGenerate($user, $space, 'claude-haiku-4-5');

        $this->assertSame(BudgetCheckResult::Allowed, $result);
    }

    public function test_author_cannot_generate_with_sonnet(): void
    {
        $user = User::factory()->create();
        $author = Role::where('slug', 'author')->first();
        $space = Space::factory()->create();

        $user->roles()->attach($author->id, ['space_id' => null]);
        $this->authz->clearCache();

        $result = $this->guard->canGenerate($user, $space, 'claude-sonnet-4-6');

        $this->assertSame(BudgetCheckResult::Denied, $result);
    }

    public function test_editor_can_generate_with_sonnet(): void
    {
        $user = User::factory()->create();
        $editor = Role::where('slug', 'editor')->first();
        $space = Space::factory()->create();

        $user->roles()->attach($editor->id, ['space_id' => null]);
        $this->authz->clearCache();

        $result = $this->guard->canGenerate($user, $space, 'claude-sonnet-4-6');

        $this->assertSame(BudgetCheckResult::Allowed, $result);
    }

    public function test_editor_cannot_generate_with_opus(): void
    {
        $user = User::factory()->create();
        $editor = Role::where('slug', 'editor')->first();
        $space = Space::factory()->create();

        $user->roles()->attach($editor->id, ['space_id' => null]);
        $this->authz->clearCache();

        $result = $this->guard->canGenerate($user, $space, 'claude-opus-4-5');

        $this->assertSame(BudgetCheckResult::Denied, $result);
    }

    public function test_admin_can_generate_with_any_model(): void
    {
        $user = User::factory()->create();
        $admin = Role::where('slug', 'admin')->first();
        $space = Space::factory()->create();

        $user->roles()->attach($admin->id, ['space_id' => null]);
        $this->authz->clearCache();

        foreach (['claude-haiku-4-5', 'claude-sonnet-4-6', 'claude-opus-4-5'] as $model) {
            $result = $this->guard->canGenerate($user, $space, $model);
            $this->assertSame(BudgetCheckResult::Allowed, $result, "Model {$model} should be allowed for admin");
        }
    }

    // ── Budget.unlimited bypass ───────────────────────────────────────────

    public function test_ai_budget_unlimited_bypasses_all_checks(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->create();

        // Create a role with ai.budget.unlimited but no daily_generations
        $unlimitedRole = Role::create([
            'name' => 'Power User',
            'slug' => 'power-user',
            'permissions' => ['ai.generate', 'ai.budget.unlimited', 'ai.model.opus'],
            'ai_limits' => ['daily_generations' => 0], // 0 would normally deny
            'is_system' => false,
        ]);

        $user->roles()->attach($unlimitedRole->id, ['space_id' => null]);
        $this->authz->clearCache();

        $result = $this->guard->canGenerate($user, $space, 'claude-opus-4-5', 999.99);

        $this->assertSame(BudgetCheckResult::Allowed, $result);
    }

    // ── NeedsApproval threshold ───────────────────────────────────────────

    public function test_high_cost_generation_returns_needs_approval(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->create();

        $role = Role::create([
            'name' => 'Test Role',
            'slug' => 'test-role-approval',
            'permissions' => ['ai.generate', 'ai.model.sonnet'],
            'ai_limits' => [
                'daily_generations' => 100,
                'monthly_cost_limit_usd' => 500.00,
                'allowed_models' => ['claude-sonnet-4-6'],
                'require_approval_above_cost_usd' => 0.50,
            ],
            'is_system' => false,
        ]);

        $user->roles()->attach($role->id, ['space_id' => null]);
        $this->authz->clearCache();

        $result = $this->guard->canGenerate($user, $space, 'claude-sonnet-4-6', 1.50);

        $this->assertSame(BudgetCheckResult::NeedsApproval, $result);
    }

    // ── Image generation ──────────────────────────────────────────────────

    public function test_author_cannot_generate_images(): void
    {
        $user = User::factory()->create();
        $author = Role::where('slug', 'author')->first();
        $space = Space::factory()->create();

        $user->roles()->attach($author->id, ['space_id' => null]);
        $this->authz->clearCache();

        $result = $this->guard->canGenerateImage($user, $space);

        $this->assertSame(BudgetCheckResult::Denied, $result);
    }

    public function test_editor_can_generate_images(): void
    {
        $user = User::factory()->create();
        $editor = Role::where('slug', 'editor')->first();
        $space = Space::factory()->create();

        $user->roles()->attach($editor->id, ['space_id' => null]);
        $this->authz->clearCache();

        $result = $this->guard->canGenerateImage($user, $space);

        $this->assertSame(BudgetCheckResult::Allowed, $result);
    }

    // ── AI limits resolution ──────────────────────────────────────────────

    public function test_most_permissive_limits_are_used_for_multi_role_user(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->create();

        $roleA = Role::create([
            'name' => 'Role A',
            'slug' => 'role-a',
            'permissions' => ['ai.generate', 'ai.model.haiku'],
            'ai_limits' => ['daily_generations' => 10, 'allowed_models' => ['claude-haiku-4-5']],
            'is_system' => false,
        ]);

        $roleB = Role::create([
            'name' => 'Role B',
            'slug' => 'role-b',
            'permissions' => ['ai.generate', 'ai.model.sonnet'],
            'ai_limits' => ['daily_generations' => 50, 'allowed_models' => ['claude-sonnet-4-6']],
            'is_system' => false,
        ]);

        $user->roles()->attach($roleA->id, ['space_id' => null]);
        $user->roles()->attach($roleB->id, ['space_id' => null]);
        $this->authz->clearCache();

        $limits = $this->authz->resolveAiLimits($user, $space);

        $this->assertEquals(50, $limits['daily_generations']);
        $this->assertContains('claude-haiku-4-5', $limits['allowed_models']);
        $this->assertContains('claude-sonnet-4-6', $limits['allowed_models']);
    }
}
