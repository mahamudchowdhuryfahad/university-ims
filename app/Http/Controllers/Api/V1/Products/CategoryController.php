<?php

namespace App\Http\Controllers\Api\V1\Products;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:categories'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $category = Category::create($validated);

        return $this->createdResponse($category, 'Category created successfully');
    }

    public function show(Category $category): JsonResponse
    {
        return $this->successResponse($category);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255', 'unique:categories,name,' . $category->id],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return $this->successResponse($category, 'Category updated successfully');
    }

   public function destroy(Category $category): JsonResponse
{
    if ($category->products()->exists()) {
        return $this->errorResponse('Cannot delete category with existing products', 422);
    }

    $category->delete();
    return $this->successResponse(null, 'Category deleted successfully');
}
}
