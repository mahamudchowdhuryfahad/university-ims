<?php

namespace App\Http\Controllers\Api\V1\FixedAssets;

use App\Http\Controllers\Controller;
use App\Models\AssetApproval;
use App\Models\AssetAssignment;
use App\Models\AssetTransfer;
use App\Models\DisposalRecord;
use App\Models\FixedAsset;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetApprovalController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $approvals = AssetApproval::with(['fixedAsset.assetCategory', 'requestedBy'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->action, fn($q, $a) => $q->where('action', $a))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($approvals);
    }

    public function requestAssign(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        if (!in_array($fixedAsset->status, ['in_store', 'available'])) {
            return $this->errorResponse('Asset is not available for assignment', 422);
        }

        if ($fixedAsset->approvals()->where('status', 'pending')->exists()) {
            return $this->errorResponse('Asset already has a pending approval request', 422);
        }

        $validated = $request->validate([
            'employee_id'   => ['required', 'exists:employees,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'room_id'       => ['nullable', 'exists:rooms,id'],
            'notes'         => ['nullable', 'string'],
        ]);

        $approval = AssetApproval::create([
            'fixed_asset_id' => $fixedAsset->id,
            'requested_by'   => auth()->id(),
            'action'         => 'assign',
            'status'         => 'pending',
            'payload'        => array_merge($validated, [
                'original_status' => $fixedAsset->status,
            ]),
        ]);

        $fixedAsset->update(['status' => 'pending_approval']);

        return $this->createdResponse(
            $approval->load(['fixedAsset', 'requestedBy']),
            'Assignment approval requested successfully'
        );
    }

    public function requestTransfer(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        if (!in_array($fixedAsset->status, ['assigned', 'available'])) {
            return $this->errorResponse('Asset cannot be transferred in its current status', 422);
        }

        if ($fixedAsset->approvals()->where('status', 'pending')->exists()) {
            return $this->errorResponse('Asset already has a pending approval request', 422);
        }

        $validated = $request->validate([
            'to_department_id' => ['nullable', 'exists:departments,id'],
            'to_room_id'       => ['nullable', 'exists:rooms,id'],
            'to_employee_id'   => ['nullable', 'exists:employees,id'],
            'transfer_date'    => ['nullable', 'date'],
            'reason'           => ['nullable', 'string'],
            'notes'            => ['nullable', 'string'],
        ]);

        $approval = AssetApproval::create([
            'fixed_asset_id' => $fixedAsset->id,
            'requested_by'   => auth()->id(),
            'action'         => 'transfer',
            'status'         => 'pending',
            'payload'        => array_merge($validated, [
                'from_department_id' => $fixedAsset->department_id,
                'from_room_id'       => $fixedAsset->room_id,
                'from_employee_id'   => $fixedAsset->employee_id,
                'original_status'    => $fixedAsset->status,
            ]),
        ]);

        $fixedAsset->update(['status' => 'pending_approval']);

        return $this->createdResponse(
            $approval->load(['fixedAsset', 'requestedBy']),
            'Transfer approval requested successfully'
        );
    }

    public function requestDistribute(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        if ($fixedAsset->status !== 'in_store') {
            return $this->errorResponse('Only in-store assets can be distributed', 422);
        }

        if ($fixedAsset->approvals()->where('status', 'pending')->exists()) {
            return $this->errorResponse('Asset already has a pending approval request', 422);
        }

        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'room_id'       => ['nullable', 'exists:rooms,id'],
            'notes'         => ['nullable', 'string'],
        ]);

        $approval = AssetApproval::create([
            'fixed_asset_id' => $fixedAsset->id,
            'requested_by'   => auth()->id(),
            'action'         => 'distribute',
            'status'         => 'pending',
            'payload'        => array_merge($validated, [
                'original_status' => $fixedAsset->status,
            ]),
        ]);

        $fixedAsset->update(['status' => 'pending_approval']);

        return $this->createdResponse(
            $approval->load(['fixedAsset', 'requestedBy']),
            'Distribution approval requested successfully'
        );
    }

    public function requestDispose(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        if ($fixedAsset->status === 'disposed') {
            return $this->errorResponse('Asset is already disposed', 422);
        }

        if ($fixedAsset->approvals()->where('status', 'pending')->exists()) {
            return $this->errorResponse('Asset already has a pending approval request', 422);
        }

        $validated = $request->validate([
            'disposal_date'  => ['required', 'date'],
            'method'         => ['required', 'in:written_off,sold,donated,scrapped,damaged'],
            'disposal_value' => ['nullable', 'numeric', 'min:0'],
            'reason'         => ['nullable', 'string'],
            'notes'          => ['nullable', 'string'],
        ]);

        $approval = AssetApproval::create([
            'fixed_asset_id' => $fixedAsset->id,
            'requested_by'   => auth()->id(),
            'action'         => 'dispose',
            'status'         => 'pending',
            'payload'        => array_merge($validated, [
                'original_status' => $fixedAsset->status,
            ]),
        ]);

        $fixedAsset->update(['status' => 'pending_approval']);

        return $this->createdResponse(
            $approval->load(['fixedAsset', 'requestedBy']),
            'Disposal approval requested successfully'
        );
    }

    public function approve(Request $request, AssetApproval $assetApproval): JsonResponse
    {
        if ($assetApproval->status !== 'pending') {
            return $this->errorResponse('This approval request is already processed', 422);
        }

        $request->validate([
            'remarks' => ['nullable', 'string'],
        ]);

        DB::beginTransaction();
        try {
            $asset   = $assetApproval->fixedAsset;
            $payload = $assetApproval->payload;

            if ($assetApproval->action === 'assign') {
                AssetAssignment::where('fixed_asset_id', $asset->id)
                    ->where('status', 'active')
                    ->update(['status' => 'returned', 'return_date' => now()]);

                AssetAssignment::create([
                    'fixed_asset_id' => $asset->id,
                    'employee_id'    => $payload['employee_id'] ?? null,
                    'department_id'  => $payload['department_id'] ?? null,
                    'room_id'        => $payload['room_id'] ?? null,
                    'assigned_date'  => now()->toDateString(),
                    'status'         => 'active',
                    'notes'          => $payload['notes'] ?? null,
                    'assigned_by'    => auth()->id(),
                ]);

                $asset->update([
                    'status'        => 'assigned',
                    'employee_id'   => $payload['employee_id'] ?? null,
                    'department_id' => $payload['department_id'] ?? null,
                    'room_id'       => $payload['room_id'] ?? null,
                ]);

            } elseif ($assetApproval->action === 'transfer') {
                AssetTransfer::create([
                    'fixed_asset_id'     => $asset->id,
                    'from_department_id' => $payload['from_department_id'] ?? null,
                    'to_department_id'   => $payload['to_department_id'] ?? null,
                    'from_room_id'       => $payload['from_room_id'] ?? null,
                    'to_room_id'         => $payload['to_room_id'] ?? null,
                    'from_employee_id'   => $payload['from_employee_id'] ?? null,
                    'to_employee_id'     => $payload['to_employee_id'] ?? null,
                    'transfer_date'      => $payload['transfer_date'] ?? now()->toDateString(),
                    'reason'             => $payload['reason'] ?? null,
                    'notes'              => $payload['notes'] ?? null,
                    'status'             => 'completed',
                    'transferred_by'     => auth()->id(),
                ]);

                $asset->update([
                    'status' => !empty($payload['to_employee_id']) ? 'assigned' : 'available',
                    'department_id' => $payload['to_department_id'] ?? $asset->department_id,
                    'room_id'       => $payload['to_room_id'] ?? null,
                    'employee_id'   => $payload['to_employee_id'] ?? null,
                ]);

            } elseif ($assetApproval->action === 'distribute') {
                $asset->update([
                    'status'        => 'available',
                    'department_id' => $payload['department_id'],
                    'room_id'       => $payload['room_id'] ?? null,
                ]);

            } elseif ($assetApproval->action === 'dispose') {
                DisposalRecord::create([
                    'fixed_asset_id' => $asset->id,
                    'disposal_date'  => $payload['disposal_date'],
                    'method'         => $payload['method'],
                    'disposal_value' => $payload['disposal_value'] ?? 0,
                    'reason'         => $payload['reason'] ?? null,
                    'disposed_by'    => auth()->id(),
                ]);

                $asset->update(['status' => 'disposed']);
            }

            $assetApproval->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'remarks'     => $request->remarks,
                'approved_at' => now(),
            ]);

            DB::commit();
            return $this->successResponse(null, 'Approval granted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage());
        }
    }

    public function reject(Request $request, AssetApproval $assetApproval): JsonResponse
    {
        if ($assetApproval->status !== 'pending') {
            return $this->errorResponse('This approval request is already processed', 422);
        }

        $request->validate([
            'remarks' => ['required', 'string'],
        ]);

        $originalStatus = $assetApproval->payload['original_status'] ?? 'in_store';
        $assetApproval->fixedAsset->update(['status' => $originalStatus]);

        $assetApproval->update([
            'status'      => 'rejected',
            'approved_by' => auth()->id(),
            'remarks'     => $request->remarks,
            'approved_at' => now(),
        ]);

        return $this->successResponse(null, 'Approval rejected successfully');
    }
}
