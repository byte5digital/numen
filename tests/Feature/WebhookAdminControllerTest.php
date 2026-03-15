<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private User $userWithPermission;

    private User $userWithoutPermission;

    private Space $anotherSpace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->space = Space::create(['name' => 'Test', 'slug' => 'test']);
        $r = Role::create(['name' => 'Mgr', 'slug' => 'mgr', 'permissions' => ['webhooks.manage']]);
        $this->userWithPermission = User::factory()->create();
        $this->userWithPermission->roles()->attach($r, ['space_id' => $this->space->id]);
        $this->userWithoutPermission = User::factory()->create();
        $this->anotherSpace = Space::create(['name' => 'Other', 'slug' => 'other']);
        $this->userWithoutPermission->roles()->attach($r, ['space_id' => $this->anotherSpace->id]);
    }

    public function test_index_unauth(): void
    {
        $this->get(route('admin.webhooks'))->assertRedirect(route('login'));
    }

    public function test_index_ok(): void
    {
        $this->actingAs($this->userWithPermission)->get(route('admin.webhooks'))->assertOk();
    }

    public function test_index_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.webhooks'))->assertForbidden();
    }

    public function test_store_unauth(): void
    {
        $this->post(route('admin.webhooks.store'), ['url' => 'https://example.com/hook', 'events' => ['c']])->assertRedirect(route('login'));
    }

    public function test_store_forbidden(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post(route('admin.webhooks.store'), ['url' => 'https://example.com/hook', 'events' => ['c']])->assertForbidden();
    }

    public function test_store_valid(): void
    {
        $this->actingAs($this->userWithPermission)->post(route('admin.webhooks.store'), ['url' => 'https://example.com/hook', 'events' => ['c']]);
        $this->assertDatabaseCount('webhooks', 1);
    }

    public function test_store_no_url(): void
    {
        $this->actingAs($this->userWithPermission)->post(route('admin.webhooks.store'), ['events' => ['c']])->assertSessionHasErrors('url');
    }

    public function test_store_no_events(): void
    {
        $this->actingAs($this->userWithPermission)->post(route('admin.webhooks.store'), ['url' => 'https://example.com/hook'])->assertSessionHasErrors('events');
    }

    public function test_store_empty_events(): void
    {
        $this->actingAs($this->userWithPermission)->post(route('admin.webhooks.store'), ['url' => 'https://example.com/hook', 'events' => []])->assertSessionHasErrors();
    }

    public function test_store_bad_url(): void
    {
        $this->actingAs($this->userWithPermission)->post(route('admin.webhooks.store'), ['url' => 'not-url', 'events' => ['c']])->assertSessionHasErrors('url');
    }

    public function test_store_local_url(): void
    {
        $this->actingAs($this->userWithPermission)->post(route('admin.webhooks.store'), ['url' => 'http://127.0.0.1/hook', 'events' => ['c']])->assertSessionHasErrors('url');
    }

    public function test_update_unauth(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $this->put(route('admin.webhooks.update', $w), ['url' => 'https://new.com/hook'])->assertRedirect(route('login'));
    }

    public function test_update_forbidden(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $this->actingAs($this->userWithoutPermission)->put(route('admin.webhooks.update', $w), ['url' => 'https://new.com/hook'])->assertForbidden();
    }

    public function test_update_url(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $this->actingAs($this->userWithPermission)->put(route('admin.webhooks.update', $w), ['url' => 'https://new.com/hook']);
    }

    public function test_update_events(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id, 'events' => ['a']]);
        $this->actingAs($this->userWithPermission)->put(route('admin.webhooks.update', $w), ['events' => ['b']]);
    }

    public function test_update_status(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id, 'is_active' => true]);
        $this->actingAs($this->userWithPermission)->put(route('admin.webhooks.update', $w), ['is_active' => false]);
    }

    public function test_destroy_unauth(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $this->delete(route('admin.webhooks.destroy', $w))->assertRedirect(route('login'));
    }

    public function test_destroy_forbidden(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $this->actingAs($this->userWithoutPermission)->delete(route('admin.webhooks.destroy', $w))->assertForbidden();
    }

    public function test_destroy_deletes(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $this->actingAs($this->userWithPermission)->delete(route('admin.webhooks.destroy', $w));
        $w->refresh();
        $this->assertSoftDeleted($w);
    }

    public function test_rotate_unauth(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $this->post(route('admin.webhooks.rotate-secret', $w))->assertRedirect(route('login'));
    }

    public function test_rotate_forbidden(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $this->actingAs($this->userWithoutPermission)->post(route('admin.webhooks.rotate-secret', $w))->assertForbidden();
    }

    public function test_rotate_changes(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $o = $w->secret;
        $this->actingAs($this->userWithPermission)->post(route('admin.webhooks.rotate-secret', $w));
        $w->refresh();
        $this->assertNotEquals($o, $w->secret);
    }

    public function test_rotate_flash(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $r = $this->actingAs($this->userWithPermission)->post(route('admin.webhooks.rotate-secret', $w));
        $r->assertSessionHas('newSecret');
    }

    public function test_deliveries_unauth(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $this->get(route('admin.webhooks.deliveries', $w))->assertRedirect(route('login'));
    }

    public function test_deliveries_forbidden(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $this->actingAs($this->userWithoutPermission)->get(route('admin.webhooks.deliveries', $w))->assertForbidden();
    }

    public function test_deliveries_json(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $this->actingAs($this->userWithPermission)->get(route('admin.webhooks.deliveries', $w))->assertOk()->assertJsonStructure(['data']);
    }

    public function test_deliveries_filters(): void
    {
        $w1 = Webhook::factory()->create(['space_id' => $this->space->id]);
        $w2 = Webhook::factory()->create(['space_id' => $this->space->id]);
        WebhookDelivery::factory()->create(['webhook_id' => $w1->id]);
        WebhookDelivery::factory()->create(['webhook_id' => $w1->id]);
        $r = $this->actingAs($this->userWithPermission)->get(route('admin.webhooks.deliveries', $w1))->json();
        $this->assertCount(2, $r['data']);
    }

    public function test_deliveries_limit(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        WebhookDelivery::factory()->count(60)->create(['webhook_id' => $w->id]);
        $r = $this->actingAs($this->userWithPermission)->get(route('admin.webhooks.deliveries', $w))->json();
        $this->assertCount(50, $r['data']);
    }

    public function test_redeliver_unauth(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $d = WebhookDelivery::factory()->create(['webhook_id' => $w->id]);
        $this->post(route('admin.webhooks.redeliver', ['id' => $w->id, 'deliveryId' => $d->id]))->assertRedirect(route('login'));
    }

    public function test_redeliver_forbidden(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $d = WebhookDelivery::factory()->create(['webhook_id' => $w->id]);
        $this->actingAs($this->userWithoutPermission)->post(route('admin.webhooks.redeliver', ['id' => $w->id, 'deliveryId' => $d->id]))->assertForbidden();
    }

    public function test_redeliver_queues(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $d = WebhookDelivery::factory()->failed()->create(['webhook_id' => $w->id]);
        $this->actingAs($this->userWithPermission)->post(route('admin.webhooks.redeliver', ['id' => $w->id, 'deliveryId' => $d->id]));
        $d->refresh();
        $this->assertEquals(WebhookDelivery::STATUS_PENDING, $d->status);
    }

    public function test_redeliver_schedules(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $d = WebhookDelivery::factory()->failed()->create(['webhook_id' => $w->id, 'scheduled_at' => null]);
        $this->actingAs($this->userWithPermission)->post(route('admin.webhooks.redeliver', ['id' => $w->id, 'deliveryId' => $d->id]));
        $d->refresh();
        $this->assertNotNull($d->scheduled_at);
    }

    public function test_redeliver_json(): void
    {
        $w = Webhook::factory()->create(['space_id' => $this->space->id]);
        $d = WebhookDelivery::factory()->failed()->create(['webhook_id' => $w->id]);
        $this->actingAs($this->userWithPermission)->post(route('admin.webhooks.redeliver', ['id' => $w->id, 'deliveryId' => $d->id]))->assertJson(['message' => 'Delivery re-queued for delivery.']);
    }
}
