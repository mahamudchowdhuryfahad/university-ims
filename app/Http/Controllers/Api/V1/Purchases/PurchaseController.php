<?php

namespace App\Http\Controllers\Api\V1\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $purchases = Purchase::with(['supplier', 'warehouse', 'createdBy'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($purchases);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id'        => ['required', 'exists:suppliers,id'],
            'warehouse_id'       => ['required', 'exists:warehouses,id'],
            'note'               => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::beginTransaction();
        try {
            $totalAmount = collect($validated['items'])->sum(fn($item) => $item['quantity'] * $item['unit_price']);

            $purchase = Purchase::create([
                'reference'    => 'PUR-' . strtoupper(Str::random(8)),
                'supplier_id'  => $validated['supplier_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'total_amount' => $totalAmount,
                'note'         => $validated['note'] ?? null,
                'created_by'   => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                ]);
            }

            DB::commit();
            return $this->createdResponse($purchase->load(['supplier', 'warehouse', 'items.product']), 'Purchase created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage());
        }
    }

    public function show(Purchase $purchase): JsonResponse
    {
        return $this->successResponse($purchase->load(['supplier', 'warehouse', 'items.product', 'createdBy']));
    }

    public function receive(Purchase $purchase): JsonResponse
    {
        if ($purchase->status === 'received') {
            return $this->errorResponse('Purchase already received');
        }

        DB::beginTransaction();
        try {
            foreach ($purchase->items as $item) {
                $stock = Stock::firstOrCreate(
                    ['product_id' => $item->product_id, 'warehouse_id' => $purchase->warehouse_id],
                    ['quantity' => 0]
                );

                $quantityBefore = $stock->quantity;
                $stock->increment('quantity', $item->quantity);

                StockMovement::create([
                    'product_id'      => $item->product_id,
                    'warehouse_id'    => $purchase->warehouse_id,
                    'type'            => 'purchase',
                    'quantity'        => $item->quantity,
                    'quantity_before' => $quantityBefore,
                    'quantity_after'  => $stock->fresh()->quantity,
                    'reference'       => $purchase->reference,
                    'created_by'      => auth()->id(),
                ]);
            }

            $purchase->update(['status' => 'received']);
            DB::commit();
            return $this->successResponse(null, 'Purchase received successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage());
        }
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        $purchase->delete();
        return $this->successResponse(null, 'Purchase deleted successfully');
    }
}
