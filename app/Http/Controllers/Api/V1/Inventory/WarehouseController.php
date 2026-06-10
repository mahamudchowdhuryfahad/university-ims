<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $warehouses = Warehouse::query()
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($warehouses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string'],
            'code'      => ['required', 'string', 'unique:warehouses'],
            'address'   => ['nullable', 'string'],
            'city'      => ['nullable', 'string'],
            'country'   => ['nullable', 'string'],
            'phone'     => ['nullable', 'string'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        $warehouse = Warehouse::create($validated);
        return $this->createdResponse($warehouse, 'Warehouse created successfully');
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return $this->successResponse($warehouse);
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['sometimes', 'string'],
            'code'      => ['sometimes', 'string', 'unique:warehouses,code,' . $warehouse->id],
            'address'   => ['nullable', 'string'],
            'city'      => ['nullable', 'string'],
            'country'   => ['nullable', 'string'],
            'phone'     => ['nullable', 'string'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        $warehouse->update($validated);
        return $this->successResponse($warehouse, 'Warehouse updated successfully');
    }

    public function destroy(Warehouse $warehouse): JsonResponse
{
    if ($warehouse->stock()->where('quantity', '>', 0)->exists()) {
        return $this->errorResponse('Cannot delete warehouse with existing stock', 422);
    }

    $warehouse->delete();
    return $this->successResponse(null, 'Warehouse deleted successfully');
}
}
