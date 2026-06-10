<?php

namespace App\Http\Controllers\Api\V1\Purchases;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
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
        $purchases = Purchase::with(['supplier', 'warehouse', 'createdBy', 'items'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($purchases);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id'               => ['required', 'exists:suppliers,id'],
            'warehouse_id'              => ['required', 'exists:warehouses,id'],
            'note'                      => ['nullable', 'string'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.product_type'      => ['required', 'in:consumable,fixed_asset'],
            'items.*.product_id'        => ['nullable', 'exists:products,id'],
            'items.*.asset_name'        => ['nullable', 'string'],
            'items.*.asset_category_id' => ['nullable', 'exists:asset_categories,id'],
            'items.*.quantity'          => ['required', 'integer', 'min:1'],
            'items.*.unit_price'        => ['required', 'numeric', 'min:0'],
        ]);

        // Cross-validation: consumable needs product_id, fixed_asset needs asset_name
        foreach ($validated['items'] as $index => $item) {
            if ($item['product_type'] === 'consumable' && empty($item['product_id'])) {
                return $this->errorResponse("Item #" . ($index + 1) . ": product_id is required for consumable items", 422);
            }
            if ($item['product_type'] === 'fixed_asset' && empty($item['asset_name'])) {
                return $this->errorResponse("Item #" . ($index + 1) . ": asset_name is required for fixed asset items", 422);
            }
        }

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
                    'purchase_id'       => $purchase->id,
                    'product_id'        => $item['product_id'] ?? null,
                    'product_type'      => $item['product_type'],
                    'asset_name'        => $item['asset_name'] ?? null,
                    'asset_category_id' => $item['asset_category_id'] ?? null,
                    'quantity'          => $item['quantity'],
                    'unit_price'        => $item['unit_price'],
                    'total_price'       => $item['quantity'] * $item['unit_price'],
                ]);
            }

            DB::commit();
            return $this->createdResponse(
                $purchase->load(['supplier', 'warehouse', 'items.product']),
                'Purchase created successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage());
        }
    }

    public function show(Purchase $purchase): JsonResponse
    {
        return $this->successResponse(
            $purchase->load(['supplier', 'warehouse', 'items.product', 'createdBy'])
        );
    }

    public function receive(Purchase $purchase): JsonResponse
    {
        if ($purchase->status === 'received') {
            return $this->errorResponse('Purchase already received', 422);
        }

        DB::beginTransaction();
        try {
            foreach ($purchase->items as $item) {

                if ($item->product_type === 'fixed_asset') {
                    for ($i = 0; $i < $item->quantity; $i++) {
                        FixedAsset::create([
                            'name'              => $item->asset_name,
                            'asset_category_id' => $item->asset_category_id,
                            'supplier_id'       => $purchase->supplier_id,
                            'purchase_date'     => now()->toDateString(),
                            'purchase_cost'     => $item->unit_price,
                            'status'            => 'in_store',
                            'condition'         => 'good',
                            'created_by'        => auth()->id(),
                        ]);
                    }
                } else {
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
        if ($purchase->status === 'received') {
            return $this->errorResponse('Cannot delete a received purchase', 422);
        }

        $purchase->delete();
        return $this->successResponse(null, 'Purchase deleted successfully');
    }
}