<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Requisition;
use App\Models\School;
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
            'total_purchases'          => Purchase::whereMonth('created_at', now()->month)->count(),
            'total_users'              => User::count(),
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

    public function lowStock(): JsonResponse
    {
        $products = Product::with(['category'])
            ->withSum('stock', 'quantity')
            ->get()
            ->filter(fn($p) => ($p->stock_sum_quantity ?? 0) <= $p->alert_quantity)
            ->take(10)
            ->values();

        return $this->successResponse($products);
    }
}