<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Buildings\BuildingController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Departments\DepartmentController;
use App\Http\Controllers\Api\V1\Employees\EmployeeController;
use App\Http\Controllers\Api\V1\FixedAssets\AssetApprovalController;
use App\Http\Controllers\Api\V1\FixedAssets\AssetCategoryController;
use App\Http\Controllers\Api\V1\FixedAssets\AssetMaintenanceController;
use App\Http\Controllers\Api\V1\FixedAssets\FixedAssetController;
use App\Http\Controllers\Api\V1\Inventory\StockController;
use App\Http\Controllers\Api\V1\Inventory\WarehouseController;
use App\Http\Controllers\Api\V1\Products\BrandController;
use App\Http\Controllers\Api\V1\Products\CategoryController;
use App\Http\Controllers\Api\V1\Products\ProductController;
use App\Http\Controllers\Api\V1\Purchases\PurchaseController;
use App\Http\Controllers\Api\V1\Reports\ReportController;
use App\Http\Controllers\Api\V1\Requisitions\RequisitionController;
use App\Http\Controllers\Api\V1\Rooms\RoomController;
use App\Http\Controllers\Api\V1\Schools\SchoolController;
use App\Http\Controllers\Api\V1\Suppliers\SupplierController;
use App\Http\Controllers\Api\V1\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Dashboard — all roles
        Route::middleware('permission:view_dashboard')->group(function () {
            Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
            Route::get('/dashboard/low-stock', [DashboardController::class, 'lowStock']);
        });

        // Users — super-admin only
        Route::middleware('role:super-admin')->group(function () {
            Route::apiResource('/users', UserController::class);
            Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
            Route::patch('/users/{user}/approve', [UserController::class, 'approve']);
        });

        // Products & Consumable Categories
        Route::middleware('permission:view_products')->group(function () {
            Route::apiResource('/products', ProductController::class);
        });
        Route::middleware('permission:view_categories')->group(function () {
            Route::apiResource('/categories', CategoryController::class);
        });
        Route::middleware('permission:view_brands')->group(function () {
            Route::apiResource('/brands', BrandController::class);
        });

        // Inventory
        Route::middleware('permission:view_warehouses')->group(function () {
            Route::apiResource('/warehouses', WarehouseController::class);
        });
        Route::middleware('permission:view_stock')->group(function () {
            Route::get('/stock', [StockController::class, 'index']);
            Route::get('/stock/movements', [StockController::class, 'movements']);
            Route::get('/stock/low', [StockController::class, 'lowStock']);
        });
        Route::middleware('permission:adjust_stock')->group(function () {
            Route::post('/stock/adjust', [StockController::class, 'adjust']);
        });

        // Suppliers
        Route::middleware('permission:view_suppliers')->group(function () {
            Route::apiResource('/suppliers', SupplierController::class);
        });

        // Purchases
        Route::middleware('permission:view_purchases')->group(function () {
            Route::apiResource('/purchases', PurchaseController::class);
            Route::patch('/purchases/{purchase}/receive', [PurchaseController::class, 'receive']);
        });

        // Reports
        Route::middleware('permission:view_reports')->group(function () {
            Route::get('/reports/sales', [ReportController::class, 'sales']);
            Route::get('/reports/purchases', [ReportController::class, 'purchases']);
            Route::get('/reports/stock', [ReportController::class, 'stock']);
            Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss']);
        });

        // University Structure
        Route::middleware('permission:view_schools')->group(function () {
            Route::apiResource('/schools', SchoolController::class);
        });
        Route::middleware('permission:view_departments')->group(function () {
            Route::apiResource('/departments', DepartmentController::class);
        });
        Route::middleware('permission:view_buildings')->group(function () {
            Route::apiResource('/buildings', BuildingController::class);
        });
        Route::middleware('permission:view_rooms')->group(function () {
            Route::apiResource('/rooms', RoomController::class);
        });
        Route::middleware('permission:view_employees')->group(function () {
            Route::apiResource('/employees', EmployeeController::class);
        });

        // Asset Categories
        Route::middleware('permission:view_asset_categories')->group(function () {
            Route::apiResource('/asset-categories', AssetCategoryController::class);
        });

        // Fixed Assets
        Route::middleware('permission:view_fixed_assets')->group(function () {
            Route::get('/fixed-assets/stats', [FixedAssetController::class, 'stats']);
            Route::apiResource('/fixed-assets', FixedAssetController::class);
            Route::get('/fixed-assets/{fixedAsset}/history', [FixedAssetController::class, 'history']);
        });
        Route::middleware('permission:assign_fixed_assets')->group(function () {
            Route::post('/fixed-assets/{fixedAsset}/assign', [FixedAssetController::class, 'assign']);
            Route::post('/fixed-assets/{fixedAsset}/return', [FixedAssetController::class, 'return']);
        });
        Route::middleware('permission:distribute_fixed_assets')->group(function () {
            Route::patch('/fixed-assets/{fixedAsset}/distribute', [FixedAssetController::class, 'distribute']);
        });
        Route::middleware('permission:transfer_fixed_assets')->group(function () {
            Route::post('/fixed-assets/{fixedAsset}/transfer', [FixedAssetController::class, 'transfer']);
        });
        Route::middleware('permission:dispose_fixed_assets')->group(function () {
            Route::post('/fixed-assets/{fixedAsset}/dispose', [FixedAssetController::class, 'dispose']);
        });

        // Asset Approvals — store-admin requests, fixed-asset-admin approves/rejects
        Route::middleware('permission:view_fixed_assets')->group(function () {
            Route::get('/asset-approvals', [AssetApprovalController::class, 'index']);
        });
        Route::middleware('permission:assign_fixed_assets')->group(function () {
            Route::post('/fixed-assets/{fixedAsset}/request-assign', [AssetApprovalController::class, 'requestAssign']);
            Route::post('/fixed-assets/{fixedAsset}/request-transfer', [AssetApprovalController::class, 'requestTransfer']);
            Route::post('/fixed-assets/{fixedAsset}/request-distribute', [AssetApprovalController::class, 'requestDistribute']);
            Route::post('/fixed-assets/{fixedAsset}/request-dispose', [AssetApprovalController::class, 'requestDispose']);
        });
        Route::middleware('permission:approve_fixed_assets')->group(function () {
            Route::patch('/asset-approvals/{assetApproval}/approve', [AssetApprovalController::class, 'approve']);
            Route::patch('/asset-approvals/{assetApproval}/reject', [AssetApprovalController::class, 'reject']);
        });

        // Asset Maintenance
        Route::middleware('permission:maintain_fixed_assets')->group(function () {
            Route::apiResource('/maintenances', AssetMaintenanceController::class);
            Route::patch('/maintenances/{assetMaintenance}/complete', [AssetMaintenanceController::class, 'complete']);
        });

        // Requisitions
        Route::middleware('permission:view_requisitions')->group(function () {
            Route::get('/requisitions', [RequisitionController::class, 'index']);
            Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show']);
        });
        Route::middleware('permission:create_requisitions')->group(function () {
            Route::post('/requisitions', [RequisitionController::class, 'store']);
        });
        Route::middleware('permission:approve_requisitions')->group(function () {
            Route::patch('/requisitions/{requisition}/approve', [RequisitionController::class, 'approve']);
        });
        Route::middleware('permission:reject_requisitions')->group(function () {
            Route::patch('/requisitions/{requisition}/reject', [RequisitionController::class, 'reject']);
        });
        Route::middleware('permission:fulfill_requisitions')->group(function () {
            Route::patch('/requisitions/{requisition}/fulfill', [RequisitionController::class, 'fulfill']);
        });

    });
});
