<?php

namespace App\Http\Controllers\Api\V1\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string'],
            'email'     => ['nullable', 'email'],
            'phone'     => ['nullable', 'string'],
            'address'   => ['nullable', 'string'],
            'city'      => ['nullable', 'string'],
            'country'   => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $supplier = Supplier::create($validated);
        return $this->createdResponse($supplier, 'Supplier created successfully');
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return $this->successResponse($supplier);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['sometimes', 'string'],
            'email'     => ['nullable', 'email'],
            'phone'     => ['nullable', 'string'],
            'address'   => ['nullable', 'string'],
            'city'      => ['nullable', 'string'],
            'country'   => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $supplier->update($validated);
        return $this->successResponse($supplier, 'Supplier updated successfully');
    }

    public function destroy(Supplier $supplier): JsonResponse
{
    if ($supplier->purchases()->exists()) {
        return $this->errorResponse('Cannot delete supplier with existing purchases', 422);
    }

    $supplier->delete();
    return $this->successResponse(null, 'Supplier deleted successfully');
}
}
