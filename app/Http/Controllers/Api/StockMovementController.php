<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StockMovement::with('product');

        // Filter berdasarkan product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter berdasarkan type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Search berdasarkan field yang tersedia
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting berdasarkan field yang valid
        $allowedSortFields = [
            'product_id',
            'type',
            'quantity',
            'previous_stock',
            'current_stock',
            'reference',
            'created_at',
            'updated_at'
        ];
        $sortBy = $request->get('sort_by', 'created_at');
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'created_at';

        $sortOrder = $request->get('sort_order', 'asc');
        $sortOrder = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'asc';

        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $perPage = min($perPage, 100); // Batasi maksimal 100 per halaman

        $stockMovements = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $stockMovements,
            'meta' => [
                'current_page' => $stockMovements->currentPage(),
                'last_page' => $stockMovements->lastPage(),
                'per_page' => $stockMovements->perPage(),
                'total' => $stockMovements->total(),
            ]
        ]);
    }
}
