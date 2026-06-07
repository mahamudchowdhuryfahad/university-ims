<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Stock;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ApiResponseTrait;

    public function sales(Request $request): JsonResponse
    {
        $sales = Sale::with(['customer'])
            ->when($request->from, fn($q, $d) => $q->whereDate('sale_date', '>=', $d))
            ->when($request->to, fn($q, $d) => $q->whereDate('sale_date', '<=', $d))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($sales);
    }

    public function purchases(Request $request): JsonResponse
    {
        $purchases = Purchase::with(['supplier'])
            ->when($request->from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($purchases);
    }

    public function stock(Request $request): JsonResponse
    {
        $stock = Stock::with(['product', 'warehouse'])
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($stock);
    }

    public function profitLoss(Request $request): JsonResponse
    {
        $revenue = Sale::when($request->from, fn($q, $d) => $q->whereDate('sale_date', '>=', $d))
            ->when($request->to, fn($q, $d) => $q->whereDate('sale_date', '<=', $d))
            ->sum('total_amount');

        $expenses = Purchase::when($request->from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->sum('total_amount');

        return $this->successResponse([
            'revenue'  => $revenue,
            'expenses' => $expenses,
            'profit'   => $revenue - $expenses,
        ]);
    }
}
