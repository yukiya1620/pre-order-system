<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user, array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'user_id' => $user->id,
            'type' => '注文受付',
            'title' => 'ご注文を受け付けました',
            'body' => 'テスト通知',
            'is_read' => false,
        ], $overrides));
    }

    public function test_guest_cannot_list_notifications(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(401);
    }

    public function test_buyer_can_list_own_notifications(): void
    {
        $buyer = User::factory()->create();
        $notification = $this->createNotification($buyer);

        $response = $this->actingAs($buyer)->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJsonCount(1, 'notifications');
        $response->assertJsonPath('notifications.0.id', $notification->id);
    }

    public function test_farmer_can_also_list_own_notifications(): void
    {
        $farmer = User::factory()->farmer()->create();
        $notification = $this->createNotification($farmer);

        $response = $this->actingAs($farmer)->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJsonPath('notifications.0.id', $notification->id);
    }

    public function test_index_does_not_include_other_users_notifications(): void
    {
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $this->createNotification($otherBuyer);

        $response = $this->actingAs($buyer)->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJsonCount(0, 'notifications');
    }

    public function test_index_orders_newest_first(): void
    {
        $buyer = User::factory()->create();
        $older = $this->createNotification($buyer);
        $older->forceFill(['created_at' => now()->subDays(2)])->save();
        $newer = $this->createNotification($buyer);

        $response = $this->actingAs($buyer)->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJsonPath('notifications.0.id', $newer->id);
        $response->assertJsonPath('notifications.1.id', $older->id);
    }

    public function test_owner_can_mark_notification_as_read(): void
    {
        $buyer = User::factory()->create();
        $notification = $this->createNotification($buyer);

        $response = $this->actingAs($buyer)->putJson('/api/v1/notifications/'.$notification->id.'/read');

        $response->assertOk();
        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_non_owner_cannot_mark_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $notification = $this->createNotification($owner);

        $response = $this->actingAs($otherBuyer)->putJson('/api/v1/notifications/'.$notification->id.'/read');

        $response->assertStatus(403);
        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_mark_all_as_read_only_affects_own_notifications(): void
    {
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $own = $this->createNotification($buyer);
        $others = $this->createNotification($otherBuyer);

        $response = $this->actingAs($buyer)->putJson('/api/v1/notifications/read-all');

        $response->assertOk();
        $this->assertTrue($own->fresh()->is_read);
        $this->assertFalse($others->fresh()->is_read);
    }

    public function test_guest_cannot_mark_as_read(): void
    {
        $buyer = User::factory()->create();
        $notification = $this->createNotification($buyer);

        $response = $this->putJson('/api/v1/notifications/'.$notification->id.'/read');

        $response->assertStatus(401);
    }
}
