<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientInventoryException;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        try {
            $order = $orders->create($request->validated('items'));
        } catch (InsufficientInventoryException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'items' => [
                        "Product {$exception->productId} does not have enough inventory for {$exception->requestedQuantity} requested item(s).",
                    ],
                ],
            ], 409);
        }

        return response()->json([
            'message' => 'Order created successfully.',
            'data' => $order,
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => $order->load('items.product'),
        ]);
    }
}
