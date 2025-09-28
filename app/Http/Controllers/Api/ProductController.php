<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        try {
            $products = Product::with('category')
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'Products retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve products',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function store(Request $request)
    {
        try {
            // DEBUG: CEK APA YANG DITERIMA
            Log::info('Request files:', $request->allFiles());
            Log::info('Request data:', $request->except('images'));

            if ($request->hasFile('images')) {
                $file = $request->file('images');
                Log::info('File details:', [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                    'isValid' => $file->isValid()
                ]);
            } else {
                Log::warning('No file found in request');
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:categories,id',
                'sku' => 'required|string|unique:products,sku',
                'stock_quantity' => 'required|integer|min:0',
                'price' => 'required|numeric|min:0',
                'cost_price' => 'nullable|numeric|min:0',
                'min_stock_level' => 'nullable|integer|min:0',
                'max_stock_level' => 'nullable|integer|min:0',
                'description' => 'nullable|string',
                'images' => 'nullable|image|max:10240' // ← PLURAL (sesuai database)
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate slug dari name
            $slug = Str::slug($request->name);
            $originalSlug = $slug;
            $counter = 1;

            // Pastikan slug unik
            while (Product::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            // Upload gambar jika ada - KONSISTEN PLURAL
            $imagePath = null;
            if ($request->hasFile('images')) { // ← PLURAL
                $image = $request->file('images'); // ← PLURAL
                $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('products', $imageName, 'public');
            }

            // Tentukan status berdasarkan stock
            $status = $this->determineStockStatus(
                $request->stock_quantity,
                $request->min_stock_level ?? 0
            );

            // Buat produk baru - KONSISTEN PLURAL
            $product = Product::create([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'sku' => $request->sku,
                'price' => $request->price,
                'cost_price' => $request->cost_price,
                'stock_quantity' => $request->stock_quantity,
                'min_stock_level' => $request->min_stock_level ?? 0,
                'max_stock_level' => $request->max_stock_level,
                'status' => $status,
                'category_id' => $request->category_id,
                'images' => $imagePath, // ← PLURAL (sesuai database)
                'is_active' => true
            ]);

            // Load relasi + accessor image_url
            $product->load('category');

            return response()->json([
                'success' => true,
                'data' => $product,
                'message' => 'Product created successfully'
            ], 201);
        } catch (\Exception $e) {
            // Hapus gambar jika ada error
            if (isset($imagePath) && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function show($id)
    {
        try {
            $product = Product::with('category')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $product,
                'message' => 'Product retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            // Validasi input
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:categories,id',
                'sku' => ['required', 'string', Rule::unique('products')->ignore($product->id)],
                'price' => 'required|numeric|min:0',
                'cost_price' => 'nullable|numeric|min:0',
                'min_stock_level' => 'nullable|integer|min:0',
                'max_stock_level' => 'nullable|integer|min:0',
                'description' => 'nullable|string',
                'images' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:10240',
                'remove_image' => 'nullable|boolean' // Tambahkan field untuk hapus gambar
            ]);

            // Validasi cost_price tidak boleh lebih besar dari price
            $validator->after(function ($validator) use ($request) {
                if (
                    $request->cost_price && $request->price &&
                    $request->cost_price > $request->price
                ) {
                    $validator->errors()->add(
                        'cost_price',
                        'Harga modal tidak boleh lebih besar dari harga jual'
                    );
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate slug baru jika name berubah
            $slug = $product->slug;
            if ($product->name !== $request->name) {
                $slug = Str::slug($request->name);
                $originalSlug = $slug;
                $counter = 1;

                while (Product::where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }

            // Penanganan gambar
            $imagePath = $product->images;

            // Hapus gambar jika diminta
            if ($request->has('remove_image') && $request->remove_image) {
                if ($product->images && Storage::disk('public')->exists($product->images)) {
                    Storage::disk('public')->delete($product->images);
                }
                $imagePath = null;
            }

            // Upload gambar baru jika ada
            if ($request->hasFile('images')) {
                // Hapus gambar lama
                if ($product->images && Storage::disk('public')->exists($product->images)) {
                    Storage::disk('public')->delete($product->images);
                }

                $image = $request->file('images');
                $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('products', $imageName, 'public');
            }

            // Update produk
            $product->update([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'sku' => $request->sku,
                'price' => $request->price,
                'cost_price' => $request->cost_price,
                'min_stock_level' => $request->min_stock_level ?? 0,
                'max_stock_level' => $request->max_stock_level,
                'category_id' => $request->category_id,
                'images' => $imagePath,
            ]);

            $product->load('category');

            return response()->json([
                'success' => true,
                'data' => $product,
                'message' => 'Product updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            // Hapus gambar jika ada
            if ($product->images && Storage::disk('public')->exists($product->images)) {
                Storage::disk('public')->delete($product->images);
            }

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Tambahkan method ini ke ProductController Anda
    public function updateStock(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            // Validasi input
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:in,out,adjustment', // Sesuaikan dengan nilai yang dikirim frontend
                'quantity' => 'required|integer|min:1',
                'reason' => 'required|string|max:255',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Hitung stok baru berdasarkan operasi
            $newStock = $product->stock_quantity;
            switch ($request->type) {
                case 'in': // Tambah stok
                    $newStock += $request->quantity;
                    break;
                case 'out': // Kurangi stok
                    if ($request->quantity > $product->stock_quantity) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Insufficient stock',
                            'errors' => ['quantity' => ['Jumlah pengurangan melebihi stok yang tersedia']]
                        ], 422);
                    }
                    $newStock -= $request->quantity;
                    break;
                case 'adjustment': // Set stok langsung
                    $newStock = $request->quantity;
                    break;
            }

            // Update stok produk
            $product->update([
                'stock_quantity' => $newStock
            ]);

            // Update status stok otomatis
            $status = $this->determineStockStatus(
                $newStock,
                $product->min_stock_level
            );

            $product->update(['status' => $status]);
            StockMovement::create([
                'product_id' => $product->id,
                'type' => $request->type,
                'quantity' => $request->quantity,
                'previous_stock' => $product->stock_quantity,
                'current_stock' => $newStock,
                'reason' => $request->reason,
                'notes' => $request->notes
            ]);

            // Reload product dengan data terbaru
            $product->load('category');

            return response()->json([
                'success' => true,
                'data' => $product,
                'message' => 'Stock updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function determineStockStatus(int $stockQuantity, int $minStockLevel): string
    {
        if ($stockQuantity <= 0) {
            return 'out_of_stock';
        }

        if ($stockQuantity <= $minStockLevel) {
            return 'low_stock';
        }

        return 'in_stock';
    }
}
