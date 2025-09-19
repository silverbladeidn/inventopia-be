<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\DB;

// === Auth ===
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout']);
// === Protected routes ===
Route::middleware('auth:sanctum')->group(function () {
    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Products
    Route::apiResource('products', ProductController::class);
    Route::post('products/{product}/update-stock', [ProductController::class, 'updateStock']);

    // Stock Movement
    Route::get('stockmovement', [StockMovementController::class, 'index']);

    // Users
    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/change-password', [UserController::class, 'changePassword']);
    Route::patch('users/{user}/block-status', [UserController::class, 'updateBlockStatus']);

    // Dashboard stats
    Route::get('dashboard/stats', function () {
        $totalProducts = \App\Models\Product::count();
        $inStock = \App\Models\Product::inStock()->count();
        $lowStock = \App\Models\Product::lowStock()->count();
        $outOfStock = \App\Models\Product::outOfStock()->count();
        $totalValue = \App\Models\Product::sum(DB::raw('stock_quantity * price'));

        return response()->json([
            'success' => true,
            'data' => [
                'total_products' => $totalProducts,
                'in_stock' => $inStock,
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
                'total_inventory_value' => $totalValue
            ]
        ]);
    });
});
