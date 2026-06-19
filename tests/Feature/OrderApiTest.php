<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_api_creates_order_items_and_decrements_discounted_inventory(): void
    {
        $product = Product::query()->create([
            'name' => 'Flash Sale Headphones',
            'inventory_quantity' => 5,
            'price' => 250_000,
            'discount_price' => 25_000,
        ]);

        $this->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Order created successfully.')
            ->assertJsonPath('data.total_amount', 50_000)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', 25_000)
            ->assertJsonPath('data.items.0.subtotal', 50_000);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'inventory_quantity' => 3,
        ]);
    }

    public function test_order_api_rejects_empty_orders(): void
    {
        $this->postJson('/api/orders', ['items' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_order_api_returns_conflict_when_inventory_is_insufficient(): void
    {
        $product = Product::query()->create([
            'name' => 'Limited Console',
            'inventory_quantity' => 1,
            'price' => 5_000_000,
            'discount_price' => 500_000,
        ]);

        $this->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ])
            ->assertConflict()
            ->assertJsonPath('message', 'Insufficient inventory for product.');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'inventory_quantity' => 1,
        ]);
    }
}
