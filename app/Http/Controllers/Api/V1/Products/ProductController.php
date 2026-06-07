<?php

namespace App\Http\Controllers\Api\V1\Products;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $products = Product::with(['category', 'brand'])
            ->withSum('stock', 'quantity')
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->when($request->category_id, fn($q, $id) => $q->where('category_id', $id))
            ->when($request->is_active, fn($q, $v) => $q->where('is_active', $v))
            ->latest()
            ->paginate($request->per_page ?? 15);

        $products->getCollection()->transform(function ($product) {
            $product->total_stock = (int) ($product->stock_sum_quantity ?? 0);
            $product->is_low_stock = $product->total_stock <= $product->alert_quantity;
            $product->image_url = $product->image ? asset('storage/' . $product->image) : asset('images/no-product.png');
            return $product;
        });

        return $this->successResponse($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'sku'            => ['nullable', 'string', 'unique:products'],
            'barcode'        => ['nullable', 'string', 'unique:products'],
            'category_id'    => ['nullable', 'exists:categories,id'],
            'brand_id'       => ['nullable', 'exists:brands,id'],
            'description'    => ['nullable', 'string'],
            'cost_price'     => ['nullable', 'numeric', 'min:0'],
            'selling_price'  => ['nullable', 'numeric', 'min:0'],
            'unit'           => ['nullable', 'string'],
            'alert_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['boolean'],
        ]);

        $validated['slug']          = Str::slug($validated['name']);
        $validated['sku']           = $validated['sku'] ?? strtoupper(Str::random(8));
        $validated['cost_price']    = $validated['cost_price'] ?? 0;
        $validated['selling_price'] = $validated['selling_price'] ?? 0;
        $validated['created_by']    = auth()->id();

        $product = Product::create($validated);

        return $this->createdResponse(
            $product->load(['category', 'brand']),
            'Product created successfully'
        );
    }

    public function show(Product $product): JsonResponse
    {
        return $this->successResponse(
            $product->load(['category', 'brand', 'stock.warehouse'])
        );
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'sku'            => ['nullable', 'string', 'unique:products,sku,' . $product->id],
            'barcode'        => ['nullable', 'string', 'unique:products,barcode,' . $product->id],
            'category_id'    => ['nullable', 'exists:categories,id'],
            'brand_id'       => ['nullable', 'exists:brands,id'],
            'description'    => ['nullable', 'string'],
            'cost_price'     => ['nullable', 'numeric', 'min:0'],
            'selling_price'  => ['nullable', 'numeric', 'min:0'],
            'unit'           => ['nullable', 'string'],
            'alert_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['boolean'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $product->update($validated);

        return $this->successResponse(
            $product->fresh(['category', 'brand']),
            'Product updated successfully'
        );
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();
        return $this->successResponse(null, 'Product deleted successfully');
    }
}
