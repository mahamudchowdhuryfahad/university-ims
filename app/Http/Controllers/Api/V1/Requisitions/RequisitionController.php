<?php

namespace App\Http\Controllers\Api\V1\Requisitions;

use App\Http\Controllers\Controller;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequisitionController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $requisitions = Requisition::with(['department', 'requestedBy', 'items.product'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->department_id, fn($q, $id) => $q->where('department_id', $id))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($requisitions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'type'          => ['required', 'in:consumable,fixed_asset'],
            'request_date'  => ['required', 'date'],
            'required_date' => ['nullable', 'date'],
            'purpose'       => ['nullable', 'string'],
            'items'         => ['nullable', 'array'],
            'items.*.product_id'         => ['nullable', 'exists:products,id'],
            'items.*.item_description'   => ['nullable', 'string'],
            'items.*.requested_quantity' => ['required_with:items.*', 'integer', 'min:1'],
        ]);

        // Consumable এ items required
        if ($validated['type'] === 'consumable') {
            if (empty($validated['items'])) {
                return $this->errorResponse('Items are required for consumable requisitions', 422);
            }
            foreach ($validated['items'] as $item) {
                if (empty($item['product_id'])) {
                    return $this->errorResponse('Product is required for each consumable item', 422);
                }
            }
        }

        $requisition = Requisition::create([
            'reference'     => 'REQ-' . strtoupper(Str::random(8)),
            'department_id' => $validated['department_id'],
            'requested_by'  => auth()->id(),
            'type'          => $validated['type'],
            'request_date'  => $validated['request_date'],
            'required_date' => $validated['required_date'] ?? null,
            'purpose'       => $validated['purpose'] ?? null,
        ]);

        foreach ($validated['items'] ?? [] as $item) {
            RequisitionItem::create([
                'requisition_id'     => $requisition->id,
                'product_id'         => $item['product_id'] ?? null,
                'item_description'   => $item['item_description'] ?? null,
                'requested_quantity' => $item['requested_quantity'],
            ]);
        }

        return $this->createdResponse(
            $requisition->load(['department', 'items.product']),
            'Requisition created successfully'
        );
    }

    public function show(Requisition $requisition): JsonResponse
    {
        return $this->successResponse(
            $requisition->load(['department', 'requestedBy', 'approvedBy', 'items.product'])
        );
    }

    public function approve(Request $request, Requisition $requisition): JsonResponse
    {
        if ($requisition->status !== 'pending') {
            return $this->errorResponse('Only pending requisitions can be approved', 422);
        }

        // Role based check
        $user = auth()->user();
        if ($user->hasRole('fixed-asset-admin') && $requisition->type !== 'fixed_asset') {
            return $this->errorResponse('You can only approve fixed asset requisitions', 403);
        }
        if ($user->hasRole('consumable-admin') && $requisition->type !== 'consumable') {
            return $this->errorResponse('You can only approve consumable requisitions', 403);
        }

        $validated = $request->validate([
            'remarks'                   => ['nullable', 'string'],
            'items'                     => ['nullable', 'array'],
            'items.*.id'                => ['required_with:items.*', 'exists:requisition_items,id'],
            'items.*.approved_quantity' => ['required_with:items.*', 'integer', 'min:0'],
        ]);

        foreach ($validated['items'] ?? [] as $item) {
            $reqItem = RequisitionItem::find($item['id']);
            $approvedQty = min($item['approved_quantity'], $reqItem->requested_quantity);
            $reqItem->update(['approved_quantity' => $approvedQty]);
        }

        $requisition->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'remarks'     => $validated['remarks'] ?? null,
            'approved_at' => now(),
        ]);

        return $this->successResponse(null, 'Requisition approved successfully');
    }

    public function reject(Request $request, Requisition $requisition): JsonResponse
    {
        if (!in_array($requisition->status, ['pending', 'approved'])) {
            return $this->errorResponse('Only pending or approved requisitions can be rejected', 422);
        }

        $request->validate([
            'remarks' => ['required', 'string'],
        ]);

        $requisition->update([
            'status'  => 'rejected',
            'remarks' => $request->remarks,
        ]);

        return $this->successResponse(null, 'Requisition rejected successfully');
    }

    public function fulfill(Request $request, Requisition $requisition): JsonResponse
    {
        if ($requisition->status !== 'approved') {
            return $this->errorResponse('Only approved requisitions can be fulfilled', 422);
        }

        // Fixed asset requisition — শুধু status fulfilled করো
        if ($requisition->type === 'fixed_asset') {
            $requisition->update(['status' => 'fulfilled']);
            return $this->successResponse(null, 'Requisition fulfilled successfully');
        }

        $validated = $request->validate([
            'items'                      => ['required', 'array'],
            'items.*.id'                 => ['required', 'exists:requisition_items,id'],
            'items.*.fulfilled_quantity' => ['required', 'integer', 'min:0'],
        ]);

        // Stock check
        foreach ($validated['items'] as $item) {
            $reqItem = RequisitionItem::find($item['id']);
            if (!$reqItem->product_id) continue;
            $fulfilledQty = min($item['fulfilled_quantity'], $reqItem->approved_quantity);
            if ($fulfilledQty <= 0) continue;

            $totalStock = Stock::where('product_id', $reqItem->product_id)->sum('quantity');
            if ($totalStock < $fulfilledQty) {
                return $this->errorResponse(
                    "Insufficient stock for product: {$reqItem->product->name}. Available: {$totalStock}, Required: {$fulfilledQty}",
                    422
                );
            }
        }

        DB::beginTransaction();
        try {
            foreach ($validated['items'] as $item) {
                $reqItem = RequisitionItem::find($item['id']);
                $fulfilledQty = min($item['fulfilled_quantity'], $reqItem->approved_quantity);

                $reqItem->update(['fulfilled_quantity' => $fulfilledQty]);

                if ($reqItem->product_id && $fulfilledQty > 0) {
                    $remaining = $fulfilledQty;

                    $stocks = Stock::where('product_id', $reqItem->product_id)
                        ->where('quantity', '>', 0)
                        ->orderBy('id')
                        ->get();

                    foreach ($stocks as $stock) {
                        if ($remaining <= 0) break;

                        $deduct = min($remaining, $stock->quantity);
                        $quantityBefore = $stock->quantity;
                        $stock->decrement('quantity', $deduct);

                        StockMovement::create([
                            'product_id'      => $reqItem->product_id,
                            'warehouse_id'    => $stock->warehouse_id,
                            'type'            => 'requisition',
                            'quantity'        => $deduct,
                            'quantity_before' => $quantityBefore,
                            'quantity_after'  => $stock->fresh()->quantity,
                            'reference'       => $requisition->reference,
                            'created_by'      => auth()->id(),
                        ]);

                        $remaining -= $deduct;
                    }
                }
            }

            $requisition->update(['status' => 'fulfilled']);

            DB::commit();
            return $this->successResponse(null, 'Requisition fulfilled successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage());
        }
    }
}
