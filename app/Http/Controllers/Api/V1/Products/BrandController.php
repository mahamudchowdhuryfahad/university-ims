<?php

namespace App\Http\Controllers\Api\V1\Products;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $brands = Brand::query()
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($brands);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:brands'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $brand = Brand::create($validated);

        return $this->createdResponse($brand, 'Brand created successfully');
    }

    public function show(Brand $brand): JsonResponse
    {
        return $this->successResponse($brand);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255', 'unique:brands,name,' . $brand->id],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $brand->update($validated);

        return $this->successResponse($brand, 'Brand updated successfully');
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();
        return $this->successResponse(null, 'Brand deleted successfully');
    }
}
