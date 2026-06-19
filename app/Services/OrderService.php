<?php

namespace App\Services;

use App\Exceptions\InsufficientInventoryException;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function create(array $items): Order
    {
        return DB::transaction(function () use ($items): Order {
            $normalizedItems = $this->normalizeItems($items);
            $products = Product::query()
                ->whereIn('id', $normalizedItems->keys())
                ->get()
                ->keyBy('id');

            $order = Order::query()->create(['total_amount' => 0]);
            $totalAmount = 0;

            foreach ($normalizedItems as $productId => $quantity) {
                $product = $products->get($productId);

                if (! $product instanceof Product) {
                    throw new InsufficientInventoryException($productId, $quantity);
                }

                $updatedRows = Product::query()
                    ->whereKey($productId)
                    ->where('inventory_quantity', '>=', $quantity)
                    ->decrement('inventory_quantity', $quantity);

                if ($updatedRows === 0) {
                    throw new InsufficientInventoryException($productId, $quantity);
                }

                $unitPrice = $product->salePrice();
                $subtotal = $unitPrice * $quantity;
                $totalAmount += $subtotal;

                $order->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
            }

            $order->update(['total_amount' => $totalAmount]);

            return $order->load('items.product');
        });
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @return Collection<int, int>
     */
    private function normalizeItems(array $items): Collection
    {
        return collect($items)
            ->groupBy('product_id')
            ->map(fn (Collection $group): int => $group->sum('quantity'));
    }
}
