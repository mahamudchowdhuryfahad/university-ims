<?php

namespace App\Http\Controllers\Api\V1\FixedAssets;

use App\Http\Controllers\Controller;
use App\Models\AssetAssignment;
use App\Models\AssetTransfer;
use App\Models\DisposalRecord;
use App\Models\FixedAsset;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FixedAssetController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $assets = FixedAsset::with(['assetCategory', 'brand', 'department', 'employee', 'room'])
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('asset_tag', 'like', "%{$s}%")->orWhere('serial_number', 'like', "%{$s}%"))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->department_id, fn($q, $id) => $q->where('department_id', $id))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($assets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string'],
            'quantity'          => ['nullable', 'integer', 'min:1'],
            'serial_number'     => ['nullable', 'string', 'unique:fixed_assets,serial_number'],
            'model'             => ['nullable', 'string'],
            'asset_category_id' => ['nullable', 'exists:asset_categories,id'],
            'brand_id'          => ['nullable', 'exists:brands,id'],
            'department_id'     => ['nullable', 'exists:departments,id'],
            'room_id'           => ['nullable', 'exists:rooms,id'],
            'purchase_date'     => ['nullable', 'date'],
            'purchase_cost'     => ['nullable', 'numeric'],
            'warranty_expiry'   => ['nullable', 'date'],
            'condition'         => ['nullable', 'in:good,fair,poor,damaged'],
            'description'       => ['nullable', 'string'],
        ]);

        $serialNumbers = $request->serial_numbers ?? [];
        $quantity = $validated['quantity'] ?? 1;
        unset($validated['quantity']);
        $validated['created_by'] = auth()->id();

        $assets = [];
        for ($i = 0; $i < $quantity; $i++) {
            $assetData = $validated;
            $assetData['serial_number'] = $serialNumbers[$i] ?? null;
            $assetData['status'] = 'in_store';
            $assets[] = FixedAsset::create($assetData);
        }

        return $this->createdResponse(
            count($assets) === 1 ? $assets[0] : $assets,
            count($assets) . ' asset(s) created successfully'
        );
    }

    public function show(FixedAsset $fixedAsset): JsonResponse
    {
        return $this->successResponse(
            $fixedAsset->load(['assetCategory', 'brand', 'department', 'employee', 'room'])
        );
    }

    public function update(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $validated = $request->validate([
            'name'              => ['sometimes', 'string'],
            'serial_number'     => ['nullable', 'string', Rule::unique('fixed_assets', 'serial_number')->ignore($fixedAsset->id)],
            'model'             => ['nullable', 'string'],
            'asset_category_id' => ['nullable', 'exists:asset_categories,id'],
            'brand_id'          => ['nullable', 'exists:brands,id'],
            'department_id'     => ['nullable', 'exists:departments,id'],
            'room_id'           => ['nullable', 'exists:rooms,id'],
            'purchase_date'     => ['nullable', 'date'],
            'purchase_cost'     => ['nullable', 'numeric'],
            'warranty_expiry'   => ['nullable', 'date'],
            'condition'         => ['nullable', 'in:good,fair,poor,damaged'],
            'description'       => ['nullable', 'string'],
        ]);

        $fixedAsset->update($validated);

        return $this->successResponse(
            $fixedAsset->fresh(['assetCategory', 'brand', 'department', 'room']),
            'Fixed asset updated successfully'
        );
    }

    public function destroy(FixedAsset $fixedAsset): JsonResponse
    {
        if (in_array($fixedAsset->status, ['assigned', 'pending_approval'])) {
            return $this->errorResponse('Cannot delete an assigned or pending approval asset', 422);
        }

        $fixedAsset->delete();
        return $this->successResponse(null, 'Fixed asset deleted successfully');
    }

    public function assign(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        if (auth()->user()->hasRole('store-admin')) {
            return $this->errorResponse('Store admin must submit an approval request for assignment', 403);
        }

        if (!in_array($fixedAsset->status, ['available', 'in_store'])) {
            return $this->errorResponse('Asset is not available for assignment', 422);
        }

        $validated = $request->validate([
            'employee_id'   => ['required', 'exists:employees,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'room_id'       => ['nullable', 'exists:rooms,id'],
            'assigned_date' => ['required', 'date'],
            'notes'         => ['nullable', 'string'],
        ]);

        AssetAssignment::where('fixed_asset_id', $fixedAsset->id)
            ->where('status', 'active')
            ->update(['status' => 'returned', 'return_date' => now()]);

        $assignment = AssetAssignment::create([
            'fixed_asset_id' => $fixedAsset->id,
            'employee_id'    => $validated['employee_id'],
            'department_id'  => $validated['department_id'] ?? null,
            'room_id'        => $validated['room_id'] ?? null,
            'assigned_date'  => $validated['assigned_date'],
            'notes'          => $validated['notes'] ?? null,
            'assigned_by'    => auth()->id(),
        ]);

        $fixedAsset->update([
            'status'        => 'assigned',
            'employee_id'   => $validated['employee_id'],
            'department_id' => $validated['department_id'] ?? $fixedAsset->department_id,
        ]);

        return $this->successResponse(
            $assignment->load(['employee', 'department']),
            'Asset assigned successfully'
        );
    }

    public function return(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        if ($fixedAsset->status !== 'assigned') {
            return $this->errorResponse('Only assigned assets can be returned', 422);
        }

        AssetAssignment::where('fixed_asset_id', $fixedAsset->id)
            ->where('status', 'active')
            ->update(['status' => 'returned', 'return_date' => $request->return_date ?? now()]);

        $fixedAsset->update([
            'status'      => 'available',
            'employee_id' => null,
            'room_id'     => null,
        ]);

        return $this->successResponse(null, 'Asset returned successfully');
    }

    public function distribute(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        if (auth()->user()->hasRole('store-admin')) {
            return $this->errorResponse('Store admin must submit an approval request for distribution', 403);
        }

        if ($fixedAsset->status !== 'in_store') {
            return $this->errorResponse('Only in-store assets can be distributed', 422);
        }

        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'room_id'       => ['nullable', 'exists:rooms,id'],
            'notes'         => ['nullable', 'string'],
        ]);

        $fixedAsset->update([
            'department_id' => $validated['department_id'],
            'room_id'       => $validated['room_id'] ?? null,
            'status'        => 'available',
        ]);

        return $this->successResponse($fixedAsset->fresh(), 'Asset distributed successfully');
    }

    public function transfer(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        if (auth()->user()->hasRole('store-admin')) {
            return $this->errorResponse('Store admin must submit an approval request for transfer', 403);
        }

        if (!in_array($fixedAsset->status, ['available', 'assigned'])) {
            return $this->errorResponse('Asset cannot be transferred in its current status', 422);
        }

        $validated = $request->validate([
            'to_department_id' => ['nullable', 'exists:departments,id'],
            'to_room_id'       => ['nullable', 'exists:rooms,id'],
            'to_employee_id'   => ['nullable', 'exists:employees,id'],
            'transfer_date'    => ['required', 'date'],
            'reason'           => ['nullable', 'string'],
            'notes'            => ['nullable', 'string'],
        ]);

        $transfer = AssetTransfer::create([
            'fixed_asset_id'     => $fixedAsset->id,
            'from_department_id' => $fixedAsset->department_id,
            'to_department_id'   => $validated['to_department_id'] ?? null,
            'from_room_id'       => $fixedAsset->room_id,
            'to_room_id'         => $validated['to_room_id'] ?? null,
            'from_employee_id'   => $fixedAsset->employee_id,
            'to_employee_id'     => $validated['to_employee_id'] ?? null,
            'transfer_date'      => $validated['transfer_date'],
            'reason'             => $validated['reason'] ?? null,
            'notes'              => $validated['notes'] ?? null,
            'transferred_by'     => auth()->id(),
        ]);

        $fixedAsset->update([
            'department_id' => $validated['to_department_id'] ?? $fixedAsset->department_id,
            'room_id'       => $validated['to_room_id'] ?? $fixedAsset->room_id,
            'employee_id'   => $validated['to_employee_id'] ?? $fixedAsset->employee_id,
            'status'        => !empty($validated['to_employee_id'] ?? null) ? 'assigned' : 'available',
        ]);

        return $this->successResponse($transfer, 'Asset transferred successfully');
    }

    public function history(FixedAsset $fixedAsset): JsonResponse
    {
        return $this->successResponse([
            'asset'        => $fixedAsset->load(['assetCategory', 'brand', 'department', 'room']),
            'assignments'  => $fixedAsset->assignments()->with(['employee', 'department'])->get(),
            'transfers'    => $fixedAsset->transfers()->with(['fromDepartment', 'toDepartment'])->get(),
            'maintenances' => $fixedAsset->maintenances()->get(),
            'disposal'     => $fixedAsset->disposal,
        ]);
    }

    public function dispose(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        if ($fixedAsset->status === 'disposed') {
            return $this->errorResponse('Asset already disposed', 422);
        }

        if ($fixedAsset->status === 'pending_approval') {
            return $this->errorResponse('Cannot dispose asset with pending approval', 422);
        }

        $validated = $request->validate([
            'disposal_date'  => ['required', 'date'],
            'method'         => ['required', 'in:written_off,sold,donated,scrapped,damaged'],
            'disposal_value' => ['nullable', 'numeric', 'min:0'],
            'reason'         => ['nullable', 'string'],
            'remarks'        => ['nullable', 'string'],
        ]);

        $disposal = DisposalRecord::create([
            'fixed_asset_id' => $fixedAsset->id,
            'disposal_date'  => $validated['disposal_date'],
            'method'         => $validated['method'],
            'disposal_value' => $validated['disposal_value'] ?? 0,
            'reason'         => $validated['reason'] ?? null,
            'remarks'        => $validated['remarks'] ?? null,
            'disposed_by'    => auth()->id(),
        ]);

        $fixedAsset->update(['status' => 'disposed']);

        return $this->successResponse($disposal, 'Asset disposed successfully');
    }

    public function stats(): JsonResponse
    {
        $stats = [
                    'total'             => FixedAsset::count(),
                    'in_store'          => FixedAsset::where('status', 'in_store')->count(),
                    'available'         => FixedAsset::where('status', 'available')->count(),
                    'assigned'          => FixedAsset::where('status', 'assigned')->count(),
                    'under_maintenance' => FixedAsset::where('status', 'under_maintenance')->count(),
                    'pending_approval'  => FixedAsset::where('status', 'pending_approval')->count(),
                    'total_transfers'   => AssetTransfer::count(),
                    'disposed'          => FixedAsset::where('status', 'disposed')->count(),
                ];

        return $this->successResponse($stats);
    }
}
