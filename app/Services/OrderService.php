<?php

namespace App\Services;

use App\Exceptions\InsufficientInventoryException;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    private const MAX_CONCURRENCY_ATTEMPTS = 10;

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function create(array $items): Order
    {
        for ($attempt = 1; $attempt <= self::MAX_CONCURRENCY_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(fn (): Order => $this->createInsideTransaction($items));
            } catch (QueryException $exception) {
                if (! $this->isTransientConcurrencyError($exception) || $attempt === self::MAX_CONCURRENCY_ATTEMPTS) {
                    throw $exception;
                }

                // Back off briefly so competing flash-sale writers can finish their transaction.
                usleep(25_000 * $attempt);
            }
        }

        throw new RuntimeException('Unable to create order after concurrency retry attempts.');
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    private function createInsideTransaction(array $items): Order
    {
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

            // Atomic conditional decrement: one SQL statement prevents overselling under concurrent orders.
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

    private function isTransientConcurrencyError(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'database is locked')
            || str_contains($message, 'database table is locked')
            || str_contains($message, 'deadlock found')
            || str_contains($message, 'deadlock detected')
            || str_contains($message, 'lock wait timeout exceeded');
    }
}
