<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Requisition;
use App\Models\Sale;
use App\Models\School;
use App\Models\Supplier;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function stats(): JsonResponse
    {
        $stats = [
            'total_products'           => Product::count(),
            'total_sales'              => Sale::whereMonth('created_at', now()->month)->count(),
            'total_purchases'          => Purchase::whereMonth('created_at', now()->month)->count(),
            'total_suppliers'          => Supplier::count(),
            'total_users'              => User::count(),
            'revenue_this_month'       => Sale::whereMonth('created_at', now()->month)->sum('total_amount'),
            'purchases_this_month'     => Purchase::whereMonth('created_at', now()->month)->sum('total_amount'),
            'profit_this_month'        => Sale::whereMonth('created_at', now()->month)->sum('total_amount') - Purchase::whereMonth('created_at', now()->month)->sum('total_amount'),
            'total_fixed_assets'       => FixedAsset::count(),
            'available_assets'         => FixedAsset::where('status', 'available')->count(),
            'assigned_assets'          => FixedAsset::where('status', 'assigned')->count(),
            'under_maintenance_assets' => FixedAsset::where('status', 'under_maintenance')->count(),
            'total_departments'        => Department::count(),
            'total_employees'          => Employee::count(),
            'pending_requisitions'     => Requisition::where('status', 'pending')->count(),
            'total_schools'            => School::count(),
        ];

        return $this->successResponse($stats);
    }

    public function recentSales(): JsonResponse
    {
        $sales = Sale::with(['customer', 'createdBy'])
            ->latest()
            ->limit(10)
            ->get();

        return $this->successResponse($sales);
    }

    public function lowStock(): JsonResponse
    {
        $products = Product::with(['category', 'stock'])
            ->withSum('stock', 'quantity')
            ->having('stock_sum_quantity', '<=', 10)
            ->orWhereDoesntHave('stock')
            ->limit(10)
            ->get();

        return $this->successResponse($products);
    }
}
