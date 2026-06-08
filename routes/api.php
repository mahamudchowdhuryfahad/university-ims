<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Buildings\BuildingController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Departments\DepartmentController;
use App\Http\Controllers\Api\V1\Employees\EmployeeController;
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
use App\Http\Controllers\Api\V1\Sales\SaleController;
use App\Http\Controllers\Api\V1\Schools\SchoolController;
use App\Http\Controllers\Api\V1\Suppliers\SupplierController;
use App\Http\Controllers\Api\V1\Users\UserController;
use App\Http\Controllers\Api\V1\FixedAssets\AssetCategoryController;
use App\Http\Controllers\Api\V1\FixedAssets\AssetMaintenanceController;
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

        // Dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/recent-sales', [DashboardController::class, 'recentSales']);
        Route::get('/dashboard/low-stock', [DashboardController::class, 'lowStock']);

        // Users
        Route::apiResource('/users', UserController::class);
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);

        // Products
        Route::apiResource('/products', ProductController::class);
        Route::apiResource('/categories', CategoryController::class);
        Route::apiResource('/brands', BrandController::class);

        // Inventory
        Route::apiResource('/warehouses', WarehouseController::class);
        Route::get('/stock', [StockController::class, 'index']);
        Route::post('/stock/adjust', [StockController::class, 'adjust']);
        Route::get('/stock/movements', [StockController::class, 'movements']);
        Route::get('/stock/low', [StockController::class, 'lowStock']);

        // Suppliers
        Route::apiResource('/suppliers', SupplierController::class);

        // Purchases
        Route::apiResource('/purchases', PurchaseController::class);
        Route::patch('/purchases/{purchase}/receive', [PurchaseController::class, 'receive']);

        // Sales
        Route::apiResource('/sales', SaleController::class);

        // Reports
        Route::get('/reports/sales', [ReportController::class, 'sales']);
        Route::get('/reports/purchases', [ReportController::class, 'purchases']);
        Route::get('/reports/stock', [ReportController::class, 'stock']);
        Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss']);

        // University IMS
        Route::apiResource('/schools', SchoolController::class);
        Route::apiResource('/departments', DepartmentController::class);
        Route::apiResource('/buildings', BuildingController::class);
        Route::apiResource('/rooms', RoomController::class);
        Route::apiResource('/employees', EmployeeController::class);

        // Fixed Assets
        Route::apiResource('/fixed-assets', FixedAssetController::class);
        Route::post('/fixed-assets/{fixedAsset}/assign', [FixedAssetController::class, 'assign']);
        Route::post('/fixed-assets/{fixedAsset}/return', [FixedAssetController::class, 'return']);
        Route::post('/fixed-assets/{fixedAsset}/transfer', [FixedAssetController::class, 'transfer']);
        Route::get('/fixed-assets/{fixedAsset}/history', [FixedAssetController::class, 'history']);
        Route::post('/fixed-assets/{fixedAsset}/dispose', [FixedAssetController::class, 'dispose']);

        // Requisitions
        Route::apiResource('/requisitions', RequisitionController::class);
        Route::patch('/requisitions/{requisition}/approve', [RequisitionController::class, 'approve']);
        Route::patch('/requisitions/{requisition}/reject', [RequisitionController::class, 'reject']);
        Route::patch('/requisitions/{requisition}/fulfill', [RequisitionController::class, 'fulfill']);

        // Asset Categories
        Route::apiResource('/asset-categories', AssetCategoryController::class);

        // Additional user management routes
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::patch('/users/{user}/approve', [UserController::class, 'approve']);

        // Asset Maintenance
        Route::apiResource('/maintenances', AssetMaintenanceController::class);
        Route::patch('/maintenances/{assetMaintenance}/complete', [AssetMaintenanceController::class, 'complete']);

    });
});
