<?php

namespace Tests\Feature;

use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_returns_published_announcements(): void
    {
        $published = Announcement::create([
            'title' => '公開中のお知らせ',
            'is_published' => true,
            'published_at' => now(),
        ]);
        Announcement::create([
            'title' => '非公開のお知らせ',
            'is_published' => false,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/announcements');

        $response->assertOk();
        $response->assertJsonCount(1, 'announcements');
        $response->assertJsonPath('announcements.0.id', $published->id);
    }

    public function test_index_orders_by_published_at_descending(): void
    {
        $older = Announcement::create([
            'title' => '古いお知らせ',
            'is_published' => true,
            'published_at' => now()->subDays(2),
        ]);
        $newer = Announcement::create([
            'title' => '新しいお知らせ',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/announcements');

        $response->assertOk();
        $response->assertJsonPath('announcements.0.id', $newer->id);
        $response->assertJsonPath('announcements.1.id', $older->id);
    }

    public function test_index_returns_empty_array_when_no_announcements(): void
    {
        $response = $this->getJson('/api/v1/announcements');

        $response->assertOk();
        $response->assertJsonCount(0, 'announcements');
    }

    public function test_index_does_not_require_authentication(): void
    {
        Announcement::create([
            'title' => '認証不要のお知らせ',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/announcements');

        $response->assertOk();
    }
}
