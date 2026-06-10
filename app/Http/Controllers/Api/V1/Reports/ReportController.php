<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Stock;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ApiResponseTrait;

    public function purchases(Request $request): JsonResponse
    {
        $purchases = Purchase::with(['supplier', 'warehouse', 'items.product'])
            ->when($request->from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->successResponse($purchases);
    }

    public function stock(Request $request): JsonResponse
    {
        $stock = Stock::with(['product', 'warehouse'])
            ->paginate($request->per_page ?? 100);

        return $this->successResponse($stock);
    }

    public function sales(): JsonResponse
    {
        return $this->errorResponse('Sales feature is not available', 404);
    }

    public function profitLoss(): JsonResponse
    {
        return $this->errorResponse('Profit/Loss report is not available', 404);
    }
}