<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\ItemRequestController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\EmailSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Models\Role;
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

    Route::get('permissions', [PermissionController::class, 'index']);

    Route::get('roles', function () {
        return response()->json(Role::all());
    });

    Route::get('roles/{role}/permissions', function (Role $role) {
        return response()->json([
            'data' => $role->permissions()->get()
        ]);
    });

    // Users
    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/change-password', [UserController::class, 'changePassword']);
    Route::patch('users/{user}/block-status', [UserController::class, 'updateBlockStatus']);

    // Get current user's permissions - ini yang akan dipanggil frontend
    Route::get('/users/{userId}/permissions', [PermissionController::class, 'getUserPermissions']);

    // Alternative: Get my own permissions
    Route::get('/my-permissions', [PermissionController::class, 'getMyPermissions']);

    // Check if user has specific permission (for admin use)
    Route::post('/users/{userId}/check-permission', [PermissionController::class, 'checkUserPermission']);

    // Item Request CRUD
    Route::get('item-requests/stats', [ItemRequestController::class, 'stats']);
    // Item Request CRUD - put this AFTER specific routes
    Route::apiResource('item-requests', ItemRequestController::class);
    Route::post('item-requests/{itemRequest}/cancel', [ItemRequestController::class, 'cancel']);
    // User specific routes
    Route::get('my-requests', [ItemRequestController::class, 'myRequests']);
    Route::get('my-requests/stats', [ItemRequestController::class, 'myStats']);

    // Email settings routes
    Route::get('/email-settings', [EmailSettingsController::class, 'index']);
    Route::put('/email-settings', [EmailSettingsController::class, 'update']);
    Route::post('/test-email', [EmailSettingsController::class, 'testEmail']);

    // Admin only routes
    Route::middleware(['role:admin'])->group(function () {
        Route::patch('item-requests/{id}/approve', [ItemRequestController::class, 'approve']);
        Route::patch('item-requests/{id}/reject', [ItemRequestController::class, 'reject']);
        Route::get('item-requests/pending/all', [ItemRequestController::class, 'pendingRequests']);
    });

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
