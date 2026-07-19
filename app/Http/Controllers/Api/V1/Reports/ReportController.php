<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Models\Purchase;
use App\Models\Stock;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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

    public function fixedAssetSummary(Request $request): JsonResponse
    {
        $query = FixedAsset::query()
            ->when($request->category_id, fn($q, $id) => $q->where('asset_category_id', $id))
            ->when($request->department_id, fn($q, $id) => $q->where('department_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s));

        $summary = [
            'total'             => (clone $query)->count(),
            'available'         => (clone $query)->where('status', 'available')->count(),
            'assigned'          => (clone $query)->where('status', 'assigned')->count(),
            'in_store'          => (clone $query)->where('status', 'in_store')->count(),
            'under_maintenance' => (clone $query)->where('status', 'under_maintenance')->count(),
            'disposed'          => (clone $query)->where('status', 'disposed')->count(),
            'pending_approval'  => (clone $query)->where('status', 'pending_approval')->count(),
            'total_cost'        => (clone $query)->sum('purchase_cost'),
        ];

        return $this->successResponse($summary);
    }

    public function fixedAssetByDepartment(Request $request): JsonResponse
    {
        $data = FixedAsset::with('department', 'assetCategory')
            ->when($request->category_id, fn($q, $id) => $q->where('asset_category_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->select('department_id', 'asset_category_id', 'status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(purchase_cost) as total_cost'))
            ->groupBy('department_id', 'asset_category_id', 'status')
            ->get();

        $grouped = [];
        foreach ($data as $row) {
            $deptId   = $row->department_id ?? 0;
            $deptName = $row->department?->name ?? 'Unassigned';
            $catName  = $row->assetCategory?->name ?? 'Uncategorized';

            if (!isset($grouped[$deptId])) {
                $grouped[$deptId] = [
                    'department_id'   => $deptId,
                    'department_name' => $deptName,
                    'total'           => 0,
                    'total_cost'      => 0,
                    'categories'      => [],
                    'by_status'       => [],
                ];
            }

            $grouped[$deptId]['total']      += $row->count;
            $grouped[$deptId]['total_cost'] += $row->total_cost;

            if (!isset($grouped[$deptId]['categories'][$catName])) {
                $grouped[$deptId]['categories'][$catName] = 0;
            }
            $grouped[$deptId]['categories'][$catName] += $row->count;

            if (!isset($grouped[$deptId]['by_status'][$row->status])) {
                $grouped[$deptId]['by_status'][$row->status] = 0;
            }
            $grouped[$deptId]['by_status'][$row->status] += $row->count;
        }

        $result = collect(array_values($grouped))->sortByDesc('total')->values();

        return $this->successResponse($result);
    }

    public function fixedAssetByRoom(Request $request): JsonResponse
    {
        $data = FixedAsset::with('room', 'department', 'assetCategory')
            ->when($request->category_id, fn($q, $id) => $q->where('asset_category_id', $id))
            ->when($request->department_id, fn($q, $id) => $q->where('department_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->select('room_id', 'department_id', 'asset_category_id',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(purchase_cost) as total_cost'))
            ->groupBy('room_id', 'department_id', 'asset_category_id')
            ->get();

        $grouped = [];
        foreach ($data as $row) {
            $roomId   = $row->room_id ?? 0;
            $roomName = $row->room ? $row->room->name . ' (' . $row->room->room_number . ')' : 'No Room';
            $catName  = $row->assetCategory?->name ?? 'Uncategorized';
            $deptName = $row->department?->name ?? 'Unassigned';

            if (!isset($grouped[$roomId])) {
                $grouped[$roomId] = [
                    'room_id'    => $roomId,
                    'room_name'  => $roomName,
                    'total'      => 0,
                    'total_cost' => 0,
                    'categories' => [],
                    'department' => $deptName,
                ];
            }

            $grouped[$roomId]['total']      += $row->count;
            $grouped[$roomId]['total_cost'] += $row->total_cost;

            if (!isset($grouped[$roomId]['categories'][$catName])) {
                $grouped[$roomId]['categories'][$catName] = 0;
            }
            $grouped[$roomId]['categories'][$catName] += $row->count;
        }

        $result = collect(array_values($grouped))->sortByDesc('total')->values();

        return $this->successResponse($result);
    }

    public function fixedAssetByCategory(Request $request): JsonResponse
    {
        $data = FixedAsset::with('assetCategory')
            ->when($request->department_id, fn($q, $id) => $q->where('department_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->select('asset_category_id', 'status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(purchase_cost) as total_cost'))
            ->groupBy('asset_category_id', 'status')
            ->get();

        $grouped = [];
        foreach ($data as $row) {
            $catId   = $row->asset_category_id ?? 0;
            $catName = $row->assetCategory?->name ?? 'Uncategorized';

            if (!isset($grouped[$catId])) {
                $grouped[$catId] = [
                    'category_id'   => $catId,
                    'category_name' => $catName,
                    'total'         => 0,
                    'total_cost'    => 0,
                    'by_status'     => [],
                ];
            }

            $grouped[$catId]['total']      += $row->count;
            $grouped[$catId]['total_cost'] += $row->total_cost;

            if (!isset($grouped[$catId]['by_status'][$row->status])) {
                $grouped[$catId]['by_status'][$row->status] = 0;
            }
            $grouped[$catId]['by_status'][$row->status] += $row->count;
        }

        $result = collect(array_values($grouped))->sortByDesc('total')->values();

        return $this->successResponse($result);
    }

    private function depreciationLineItem(FixedAsset $asset): array
    {
        $openingBalance = $asset->last_audited_accumulated_depreciation !== null
            ? (float) $asset->last_audited_accumulated_depreciation
            : 0.0;

        $accumulatedDepreciation = (float) $asset->accumulated_depreciation;
        $depreciationDuringPeriod = round($accumulatedDepreciation - $openingBalance, 2);

        return [
            'id'                        => $asset->id,
            'asset_tag'                 => $asset->asset_tag,
            'name'                      => $asset->name,
            'serial_number'             => $asset->serial_number,
            'category'                  => $asset->assetCategory?->name,
            'department'                => $asset->department?->name,
            'room'                      => $asset->room?->room_number,
            'brand'                     => $asset->brand?->name,
            'purchase_date'             => $asset->purchase_date?->format('Y-m-d'),
            'purchase_cost'             => (float) $asset->purchase_cost,
            'depreciation_rate'         => (float) $asset->depreciation_rate,
            'years_in_use'              => round($asset->years_in_use, 2),
            'opening_balance'           => round($openingBalance, 2),
            'depreciation_during_period'=> $depreciationDuringPeriod,
            'accumulated_depreciation'  => round($accumulatedDepreciation, 2),
            'current_value'             => (float) $asset->current_value,
            'status'                    => $asset->status,
            'last_audit_date'           => $asset->last_audit_date?->format('Y-m-d'),
        ];
    }

    public function depreciationReport(Request $request): JsonResponse
    {
        $assets = FixedAsset::with(['assetCategory', 'department', 'room', 'brand'])
            ->when($request->category_id, fn($q, $id) => $q->where('asset_category_id', $id))
            ->when($request->department_id, fn($q, $id) => $q->where('department_id', $id))
            ->whereNotNull('purchase_date')
            ->whereNotNull('purchase_cost')
            ->orderBy('department_id')
            ->get();

        $data = $assets->map(fn($asset) => $this->depreciationLineItem($asset));

        $summary = [
            'total_purchase_cost' => round($assets->sum('purchase_cost'), 2),
            'total_opening_balance' => round($data->sum('opening_balance'), 2),
            'total_depreciation_during_period' => round($data->sum('depreciation_during_period'), 2),
            'total_current_value' => round($data->sum('current_value'), 2),
            'total_depreciation'  => round($data->sum('accumulated_depreciation'), 2),
            'asset_count'         => $assets->count(),
        ];

        return $this->successResponse([
            'summary' => $summary,
            'assets'  => $data,
        ]);
    }

    public function depreciationExport(Request $request)
    {
        $type = $request->type ?? 'excel';

        $assets = FixedAsset::with(['assetCategory', 'department', 'room', 'brand'])
            ->when($request->category_id, fn($q, $id) => $q->where('asset_category_id', $id))
            ->when($request->department_id, fn($q, $id) => $q->where('department_id', $id))
            ->whereNotNull('purchase_date')
            ->whereNotNull('purchase_cost')
            ->orderBy('department_id')
            ->get();

        if ($type === 'excel') {
            return $this->exportDepreciationExcel($assets);
        }

        return $this->exportDepreciationPdf($assets);
    }

    private function exportDepreciationExcel($assets)
    {
        $filename = 'depreciation-report-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($assets) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Sl. No.', 'Date/Year of purchase', 'Type of asset', 'Particular', 'Qty',
                'Location', 'User Department', 'New id no', 'Vendors name',
                'Invoice price', 'Opening balance', 'Rate of depreciation',
                'Depreciation during the period', 'Accumulated depreciation', 'WDV', 'Status',
            ]);

            $sl = 1;
            foreach ($assets as $asset) {
                $item = $this->depreciationLineItem($asset);
                fputcsv($handle, [
                    $sl++,
                    $item['purchase_date'],
                    $item['category'] ?? '-',
                    $item['name'],
                    1,
                    $item['room'] ?? '-',
                    $item['department'] ?? '-',
                    $item['serial_number'] ?? '-',
                    $item['brand'] ?? '-',
                    $item['purchase_cost'],
                    $item['opening_balance'],
                    ($item['depreciation_rate'] * 100) . '%',
                    $item['depreciation_during_period'],
                    $item['accumulated_depreciation'],
                    $item['current_value'],
                    ucfirst(str_replace('_', ' ', $item['status'])),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['This report was automatically generated by the CIU Inventory Management System (IMS).']);
            fputcsv($handle, ['(c) ' . now()->format('Y') . ' Chittagong Independent University. All rights reserved. Unauthorized reproduction or distribution of this report is prohibited.']);
            fputcsv($handle, ['Developed and maintained by the CIU Software Development Team.']);
            fputcsv($handle, ['Report generated on: ' . now()->format('d M Y, h:i A')]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportDepreciationPdf($assets)
    {
        $filename = 'depreciation-report-' . now()->format('Y-m-d') . '.html';

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>Depreciation Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
            h1 { color: #1A3A6B; font-size: 16px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
            th { background: #1A3A6B; color: white; padding: 6px 8px; text-align: left; font-size: 9px; }
            td { padding: 5px 8px; border-bottom: 1px solid #eee; font-size: 9px; }
            tr:nth-child(even) { background: #f9f9f9; }
            .summary { background: #f0f4ff; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
            @media print { .no-print { display: none; } }
        </style></head><body>';

        $items = $assets->map(fn($asset) => $this->depreciationLineItem($asset));

        $totalCost = $assets->sum('purchase_cost');
        $totalOpening = $items->sum('opening_balance');
        $totalDepPeriod = $items->sum('depreciation_during_period');
        $totalDep = $items->sum('accumulated_depreciation');
        $totalWDV = $items->sum('current_value');

        $html .= '<h1>CIU Fixed Asset Depreciation Report</h1>';
        $html .= '<div class="summary">';
        $html .= '<strong>Generated:</strong> ' . now()->format('d M Y, h:i A') . ' &nbsp;|&nbsp; ';
        $html .= '<strong>Total Assets:</strong> ' . $assets->count() . ' &nbsp;|&nbsp; ';
        $html .= '<strong>Total Purchase Cost:</strong> Tk ' . number_format($totalCost) . ' &nbsp;|&nbsp; ';
        $html .= '<strong>Total WDV:</strong> Tk ' . number_format($totalWDV);
        $html .= '</div>';

        $html .= '<table><thead><tr>
            <th>#</th><th>Asset Tag</th><th>Name</th><th>Department</th>
            <th>Purchase Date</th><th>Cost</th><th>Opening Balance</th><th>Rate</th>
            <th>Depreciation</th><th>Accumulated</th><th>WDV</th>
        </tr></thead><tbody>';

        foreach ($items as $i => $item) {
            $html .= "<tr>
                <td>" . ($i + 1) . "</td>
                <td>{$item['asset_tag']}</td>
                <td>{$item['name']}</td>
                <td>" . ($item['department'] ?? '-') . "</td>
                <td>" . $item['purchase_date'] . "</td>
                <td>" . number_format($item['purchase_cost']) . "</td>
                <td>" . number_format($item['opening_balance']) . "</td>
                <td>" . ($item['depreciation_rate'] * 100) . "%</td>
                <td>" . number_format($item['depreciation_during_period']) . "</td>
                <td>" . number_format($item['accumulated_depreciation']) . "</td>
                <td>" . number_format($item['current_value']) . "</td>
            </tr>";
        }

        $html .= "<tr style='font-weight:bold; background:#f0f4ff;'>
            <td colspan='6'>Total</td>
            <td>" . number_format($totalOpening) . "</td>
            <td></td>
            <td>" . number_format($totalDepPeriod) . "</td>
            <td>" . number_format($totalDep) . "</td>
            <td>" . number_format($totalWDV) . "</td>
        </tr>";

        $html .= '</tbody></table>';
        $html .= '<div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #ccc; font-size: 9px; color: #666; text-align: center;">';
        $html .= '<p>This report was automatically generated by the CIU Inventory Management System (IMS).</p>';
        $html .= '<p>&copy; ' . now()->format('Y') . ' Chittagong Independent University. All rights reserved. Unauthorized reproduction or distribution of this report is prohibited.</p>';
        $html .= '<p>Developed and maintained by CIU Software Team.</p>';
        $html .= '<p>Report generated on: ' . now()->format('d M Y, h:i A') . '</p>';
        $html .= '</div>';
        $html .= '<script>window.onload = function() { window.print(); }</script>';
        $html .= '</body></html>';

        return response($html, 200, [
            'Content-Type'        => 'text/html; charset=UTF-8',
            // 'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    public function fixedAssetExport(Request $request)
    {
        $type      = $request->type ?? 'excel';
        $groupBy   = $request->group_by ?? 'department';

        $assets = FixedAsset::with(['assetCategory', 'department', 'room', 'employee', 'supplier'])
            ->when($request->category_id, fn($q, $id) => $q->where('asset_category_id', $id))
            ->when($request->department_id, fn($q, $id) => $q->where('department_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('department_id')
            ->orderBy('asset_category_id')
            ->get();

        if ($type === 'excel') {
            return $this->exportExcel($assets, $groupBy);
        }

        return $this->exportPdf($assets, $groupBy);
    }

    private function exportExcel($assets, $groupBy)
    {
        $filename = 'fixed-assets-report-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($assets, $groupBy) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'SL', 'Asset Tag', 'CIU IMS ID', 'Name', 'Category',
                'Department', 'Room', 'Supplier/Vendor', 'Status', 'Condition',
                'Purchase Date', 'Purchase Cost (BDT)',
            ]);

            $sl = 1;
            foreach ($assets as $asset) {
                fputcsv($handle, [
                    $sl++,
                    $asset->asset_tag,
                    $asset->serial_number ?? '-',
                    $asset->name,
                    $asset->assetCategory?->name ?? '-',
                    $asset->department?->name ?? '-',
                    $asset->room ? $asset->room->name . ' (' . $asset->room->room_number . ')' : '-',
                    $asset->supplier?->name ?? '-',
                    ucfirst(str_replace('_', ' ', $asset->status)),
                    ucfirst($asset->condition ?? '-'),
                    $asset->purchase_date ?? '-',
                    $asset->purchase_cost ?? 0,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportPdf($assets, $groupBy)
    {
        $filename = 'fixed-assets-report-' . now()->format('Y-m-d') . '.html';

        $grouped = [];
        foreach ($assets as $asset) {
            $key = match ($groupBy) {
                'room'     => $asset->room ? $asset->room->name . ' (' . $asset->room->room_number . ')' : 'No Room',
                'category' => $asset->assetCategory?->name ?? 'Uncategorized',
                default    => $asset->department?->name ?? 'Unassigned',
            };
            $grouped[$key][] = $asset;
        }
        ksort($grouped);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>Fixed Asset Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
            h1 { color: #1A3A6B; font-size: 16px; }
            h2 { color: #1A3A6B; font-size: 13px; margin-top: 20px; border-bottom: 2px solid #F5A623; padding-bottom: 4px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
            th { background: #1A3A6B; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
            td { padding: 5px 8px; border-bottom: 1px solid #eee; font-size: 10px; }
            tr:nth-child(even) { background: #f9f9f9; }
            .summary { background: #f0f4ff; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
            .badge { padding: 2px 6px; border-radius: 3px; font-size: 9px; }
            .available { background: #d1fae5; color: #065f46; }
            .assigned { background: #dbeafe; color: #1e40af; }
            .in_store { background: #ede9fe; color: #5b21b6; }
            .disposed { background: #fee2e2; color: #991b1b; }
            @media print { .no-print { display: none; } }
        </style></head><body>';

        $html .= '<h1>CIU Fixed Asset Report</h1>';
        $html .= '<div class="summary">';
        $html .= '<strong>Generated:</strong> ' . now()->format('d M Y, h:i A') . ' &nbsp;|&nbsp; ';
        $html .= '<strong>Total Assets:</strong> ' . $assets->count() . ' &nbsp;|&nbsp; ';
        $html .= '<strong>Grouped by:</strong> ' . ucfirst($groupBy);
        $html .= '</div>';

        foreach ($grouped as $groupName => $groupAssets) {
            $html .= "<h2>{$groupName} (" . count($groupAssets) . " assets)</h2>";
            $html .= '<table><thead><tr>
                <th>#</th><th>Asset Tag</th><th>CIU IMS ID</th><th>Name</th>
                <th>Category</th><th>Department</th><th>Room</th>
                <th>Status</th><th>Cost (BDT)</th>
            </tr></thead><tbody>';

            foreach ($groupAssets as $i => $asset) {
                $status = str_replace('_', ' ', $asset->status);
                $statusClass = $asset->status;
                $html .= "<tr>
                    <td>" . ($i + 1) . "</td>
                    <td>{$asset->asset_tag}</td>
                    <td>" . ($asset->serial_number ?? '-') . "</td>
                    <td>{$asset->name}</td>
                    <td>" . ($asset->assetCategory?->name ?? '-') . "</td>
                    <td>" . ($asset->department?->name ?? '-') . "</td>
                    <td>" . ($asset->room ? $asset->room->room_number : '-') . "</td>
                    <td><span class='badge {$statusClass}'>" . ucfirst($status) . "</span></td>
                    <td>" . number_format($asset->purchase_cost ?? 0) . "</td>
                </tr>";
            }

            $total_cost = collect($groupAssets)->sum('purchase_cost');
            $html .= "<tr style='font-weight:bold; background:#f0f4ff;'>
                <td colspan='8'>Total</td>
                <td>" . number_format($total_cost) . "</td>
            </tr>";

            $html .= '</tbody></table>';
        }

        $html .= '<script>window.onload = function() { window.print(); }</script>';
        $html .= '</body></html>';

        return response($html, 200, [
            'Content-Type'        => 'text/html; charset=UTF-8',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
