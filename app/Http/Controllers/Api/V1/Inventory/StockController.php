<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $stock = Stock::with(['product', 'warehouse'])
            ->when($request->warehouse_id, fn($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->product_id, fn($q, $id) => $q->where('product_id', $id))
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($stock);
    }

    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'   => ['required', 'exists:products,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'quantity'     => ['required', 'integer', 'min:1'],
            'type'         => ['required', 'in:add,subtract'],
            'note'         => ['nullable', 'string'],
        ]);

        $stock = Stock::firstOrCreate(
            ['product_id' => $validated['product_id'], 'warehouse_id' => $validated['warehouse_id']],
            ['quantity' => 0]
        );

        $quantityBefore = $stock->quantity;

        if ($validated['type'] === 'add') {
            $stock->increment('quantity', $validated['quantity']);
        } else {
            if ($stock->quantity < $validated['quantity']) {
                return $this->errorResponse('Insufficient stock', 400);
            }
            $stock->decrement('quantity', $validated['quantity']);
        }

        StockMovement::create([
            'product_id'      => $validated['product_id'],
            'warehouse_id'    => $validated['warehouse_id'],
            'type'            => $validated['type'],
            'quantity'        => $validated['quantity'],
            'quantity_before' => $quantityBefore,
            'quantity_after'  => $stock->fresh()->quantity,
            'notes'           => $validated['note'] ?? null,
            'created_by'      => auth()->id(),
        ]);

        return $this->successResponse(null, 'Stock adjusted successfully');
    }

    public function movements(Request $request): JsonResponse
    {
        $movements = StockMovement::with(['product', 'warehouse', 'createdBy'])
            ->when($request->product_id, fn($q, $id) => $q->where('product_id', $id))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($movements);
    }

    public function lowStock(): JsonResponse
    {
        $stock = Stock::with(['product', 'warehouse'])
            ->whereHas('product', fn($q) => $q->whereColumn('stocks.quantity', '<=', 'products.alert_quantity'))
            ->get();

        return $this->successResponse($stock);
    }
}
