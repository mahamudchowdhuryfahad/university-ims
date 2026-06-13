<?php

namespace App\Http\Controllers\Api\V1\FixedAssets;

use App\Http\Controllers\Controller;
use App\Models\AssetMaintenance;
use App\Models\FixedAsset;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetMaintenanceController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $maintenances = AssetMaintenance::with(['fixedAsset', 'supplier'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->fixed_asset_id, fn($q, $id) => $q->where('fixed_asset_id', $id))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($maintenances);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fixed_asset_id'   => ['required', 'exists:fixed_assets,id'],
            'type'             => ['required', 'in:repair,service,inspection,upgrade'],
            'maintenance_date' => ['required', 'date'],
            'completion_date'  => ['nullable', 'date'],
            'supplier_id'      => ['nullable', 'exists:suppliers,id'],
            'cost'             => ['nullable', 'numeric', 'min:0'],
            'status'           => ['nullable', 'in:pending,in_progress,completed'],
            'description'      => ['nullable', 'string'],
            'remarks'          => ['nullable', 'string'],
        ]);

        $asset = FixedAsset::find($validated['fixed_asset_id']);

        if (!$asset) {
            return $this->errorResponse('Asset not found', 404);
        }

        if (in_array($asset->status, ['disposed', 'under_maintenance'])) {
            return $this->errorResponse('Asset is ' . $asset->status . ' and cannot be sent for maintenance', 422);
        }

        $validated['created_by'] = auth()->id();

        $asset->update(['status' => 'under_maintenance']);

        $maintenance = AssetMaintenance::create($validated);

        return $this->createdResponse(
            $maintenance->load(['fixedAsset', 'supplier']),
            'Maintenance record created successfully'
        );
    }

    public function show(AssetMaintenance $assetMaintenance): JsonResponse
    {
        return $this->successResponse(
            $assetMaintenance->load(['fixedAsset', 'supplier'])
        );
    }

    public function update(Request $request, AssetMaintenance $assetMaintenance): JsonResponse
    {
        if ($assetMaintenance->status === 'completed') {
            return $this->errorResponse('Cannot edit a completed maintenance record', 422);
        }

        $validated = $request->validate([
            'type'             => ['sometimes', 'in:repair,service,inspection,upgrade'],
            'maintenance_date' => ['sometimes', 'date'],
            'completion_date'  => ['nullable', 'date'],
            'supplier_id'      => ['nullable', 'exists:suppliers,id'],
            'cost'             => ['nullable', 'numeric', 'min:0'],
            'status'           => ['nullable', 'in:pending,in_progress,completed'],
            'description'      => ['nullable', 'string'],
            'remarks'          => ['nullable', 'string'],
        ]);

        $newStatus = $validated['status'] ?? $assetMaintenance->status;
        $asset = FixedAsset::find($assetMaintenance->fixed_asset_id);

        $assetMaintenance->update($validated);

        if ($asset) {
            if ($newStatus === 'completed') {
                $asset->update(['status' => 'available']);
            } else {
                $asset->update(['status' => 'under_maintenance']);
            }
        }

        return $this->successResponse(
            $assetMaintenance->fresh(['fixedAsset', 'supplier']),
            'Maintenance record updated successfully'
        );
    }

    public function complete(Request $request, AssetMaintenance $assetMaintenance): JsonResponse
    {
        if ($assetMaintenance->status === 'completed') {
            return $this->errorResponse('Maintenance already completed', 422);
        }

        $validated = $request->validate([
            'completion_date' => ['required', 'date'],
            'cost'            => ['nullable', 'numeric', 'min:0'],
            'remarks'         => ['nullable', 'string'],
            'asset_condition' => ['nullable', 'in:good,fair,poor,damaged'],
        ]);

        $assetMaintenance->update([
            'status'          => 'completed',
            'completion_date' => $validated['completion_date'],
            'cost'            => $validated['cost'] ?? $assetMaintenance->cost,
            'remarks'         => $validated['remarks'] ?? $assetMaintenance->remarks,
        ]);

        $asset = FixedAsset::find($assetMaintenance->fixed_asset_id);
        if ($asset) {
            $asset->update([
                'status'    => 'available',
                'condition' => $validated['asset_condition'] ?? $asset->condition,
            ]);
        }

        return $this->successResponse(
            $assetMaintenance->fresh(['fixedAsset']),
            'Maintenance completed successfully'
        );
    }

    public function destroy(AssetMaintenance $assetMaintenance): JsonResponse
    {
        if ($assetMaintenance->status === 'in_progress') {
            return $this->errorResponse('Cannot delete an in-progress maintenance record', 422);
        }

        $asset = FixedAsset::find($assetMaintenance->fixed_asset_id);
        if ($asset && $assetMaintenance->status === 'pending') {
            $asset->update(['status' => 'available']);
        }

        $assetMaintenance->delete();
        return $this->successResponse(null, 'Maintenance record deleted successfully');
    }
}
