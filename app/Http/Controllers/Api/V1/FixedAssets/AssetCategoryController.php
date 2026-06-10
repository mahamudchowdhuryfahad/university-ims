<?php

namespace App\Http\Controllers\Api\V1\FixedAssets;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetCategoryController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $categories = AssetCategory::query()
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->withCount('fixedAssets')
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'unique:asset_categories,code'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $category = AssetCategory::create($validated);
        return $this->createdResponse($category, 'Asset category created successfully');
    }

    public function show(AssetCategory $assetCategory): JsonResponse
    {
        return $this->successResponse($assetCategory);
    }

    public function update(Request $request, AssetCategory $assetCategory): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'unique:asset_categories,code,' . $assetCategory->id],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $assetCategory->update($validated);
        return $this->successResponse($assetCategory, 'Asset category updated successfully');
    }

    public function destroy(AssetCategory $assetCategory): JsonResponse
    {
        if ($assetCategory->fixedAssets()->exists()) {
            return $this->errorResponse('Cannot delete category with existing assets', 422);
        }

        $assetCategory->delete();
        return $this->successResponse(null, 'Asset category deleted successfully');
    }
}
