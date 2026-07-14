<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerCategoriesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_categories(): void
    {
        $response = $this->getJson('/api/v1/farmer/categories');

        $response->assertStatus(401);
    }

    public function test_buyer_cannot_list_categories(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->getJson('/api/v1/farmer/categories');

        $response->assertStatus(403);
    }

    public function test_farmer_can_list_categories_ordered_by_id(): void
    {
        $farmer = User::factory()->farmer()->create();
        $second = Category::create(['name' => '定番商品', 'display_order' => 2]);
        $first = Category::create(['name' => '季節商品', 'display_order' => 1]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/categories');

        $response->assertOk();
        $response->assertJsonPath('categories.0.id', $second->id);
        $response->assertJsonPath('categories.1.id', $first->id);
    }

    public function test_categories_response_only_contains_id_and_name(): void
    {
        $farmer = User::factory()->farmer()->create();
        Category::create(['name' => '季節商品', 'display_order' => 1]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/categories');

        $response->assertOk();
        $response->assertJsonStructure(['categories' => [['id', 'name']]]);
        $response->assertJsonMissingPath('categories.0.display_order');
    }
}
