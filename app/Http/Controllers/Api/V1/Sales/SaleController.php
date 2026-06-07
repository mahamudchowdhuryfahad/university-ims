<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $sales = Sale::with(['customer', 'warehouse', 'createdBy'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($sales);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id'        => ['nullable', 'exists:customers,id'],
            'warehouse_id'       => ['required', 'exists:warehouses,id'],
            'sale_date'          => ['required', 'date'],
            'payment_method'     => ['nullable', 'string'],
            'notes'              => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::beginTransaction();
        try {
            $totalAmount = collect($validated['items'])->sum(fn($item) => $item['quantity'] * $item['unit_price']);

            $sale = Sale::create([
                'invoice_number' => 'INV-' . strtoupper(Str::random(8)),
                'customer_id'    => $validated['customer_id'] ?? null,
                'warehouse_id'   => $validated['warehouse_id'],
                'sale_date'      => $validated['sale_date'],
                'payment_method' => $validated['payment_method'] ?? null,
                'total_amount'   => $totalAmount,
                'subtotal'       => $totalAmount,
                'notes'          => $validated['notes'] ?? null,
                'created_by'     => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                SaleItem::create([
                    'sale_id'     => $sale->id,
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                ]);

                $stock = Stock::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->first();

                if ($stock) {
                    $quantityBefore = $stock->quantity;
                    $stock->decrement('quantity', $item['quantity']);

                    StockMovement::create([
                        'product_id'      => $item['product_id'],
                        'warehouse_id'    => $validated['warehouse_id'],
                        'type'            => 'sale',
                        'quantity'        => $item['quantity'],
                        'quantity_before' => $quantityBefore,
                        'quantity_after'  => $stock->fresh()->quantity,
                        'reference'       => $sale->invoice_number,
                        'created_by'      => auth()->id(),
                    ]);
                }
            }

            DB::commit();
            return $this->createdResponse($sale->load(['customer', 'items.product']), 'Sale created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage());
        }
    }

    public function show(Sale $sale): JsonResponse
    {
        return $this->successResponse($sale->load(['customer', 'warehouse', 'items.product', 'createdBy']));
    }

    public function destroy(Sale $sale): JsonResponse
    {
        $sale->delete();
        return $this->successResponse(null, 'Sale deleted successfully');
    }
}
