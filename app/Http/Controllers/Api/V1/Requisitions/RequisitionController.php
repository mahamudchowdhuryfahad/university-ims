<?php

namespace App\Http\Controllers\Api\V1\Requisitions;

use App\Http\Controllers\Controller;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'department_id'              => ['required', 'exists:departments,id'],
            'type'                       => ['required', 'in:consumable,fixed_asset'],
            'request_date'               => ['required', 'date'],
            'required_date'              => ['nullable', 'date'],
            'purpose'                    => ['nullable', 'string'],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.product_id'         => ['required', 'exists:products,id'],
            'items.*.requested_quantity' => ['required', 'integer', 'min:1'],
        ]);

        $requisition = Requisition::create([
            'reference'     => 'REQ-' . strtoupper(Str::random(8)),
            'department_id' => $validated['department_id'],
            'requested_by'  => auth()->id(),
            'type'          => $validated['type'],
            'request_date'  => $validated['request_date'],
            'required_date' => $validated['required_date'] ?? null,
            'purpose'       => $validated['purpose'] ?? null,
        ]);

        foreach ($validated['items'] as $item) {
            RequisitionItem::create([
                'requisition_id'     => $requisition->id,
                'product_id'         => $item['product_id'],
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

        $validated = $request->validate([
            'remarks'                   => ['nullable', 'string'],
            'items'                     => ['required', 'array'],
            'items.*.id'                => ['required', 'exists:requisition_items,id'],
            'items.*.approved_quantity' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['items'] as $item) {
            $reqItem = RequisitionItem::find($item['id']);

            // approved_quantity cannot exceed requested_quantity
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

        $validated = $request->validate([
            'items'                      => ['required', 'array'],
            'items.*.id'                 => ['required', 'exists:requisition_items,id'],
            'items.*.fulfilled_quantity' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['items'] as $item) {
            $reqItem = RequisitionItem::find($item['id']);

            // fulfilled_quantity cannot exceed approved_quantity
            $fulfilledQty = min($item['fulfilled_quantity'], $reqItem->approved_quantity);

            $reqItem->update(['fulfilled_quantity' => $fulfilledQty]);
        }

        $requisition->update(['status' => 'fulfilled']);

        return $this->successResponse(null, 'Requisition fulfilled successfully');
    }
}