<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockMovement;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $electronics = Category::where('slug', 'electronics')->first();
        $furnitur = Category::where('slug', 'furnitur')->first();
        $atk = Category::where('slug', 'atk')->first();

        $products = [
            [
                'name' => 'Monitor LCD LG',
                'slug' => 'monitor-lcd-lg',
                'description' => 'Monitor LCD LG 24 inch dengan resolusi Full HD',
                'sku' => 'MLC-001',
                'price' => 1000000,
                'cost_price' => 500000,
                'stock_quantity' => 45,
                'min_stock_level' => 10,
                'max_stock_level' => 100,
                'category_id' => $electronics->id,
                'status' => 'in_stock'
            ],
            [
                'name' => 'Meja Kayu',
                'slug' => 'meja-kayu',
                'description' => 'Meja untuk kantor dari kayu jati berkualitas',
                'sku' => 'MK-002',
                'price' => 250000,
                'cost_price' => 200000,
                'stock_quantity' => 0,
                'min_stock_level' => 5,
                'max_stock_level' => 20,
                'category_id' => $furnitur->id,
                'status' => 'out_of_stock'
            ],
            [
                'name' => 'Pensil 2B Faber-Castell',
                'slug' => 'pensil-2b-fc',
                'description' => 'Pensil 2B dari Faber-Castell, cocok untuk menggambar dan menulis',
                'sku' => 'CM-004',
                'price' => 1000,
                'cost_price' => 500,
                'stock_quantity' => 23,
                'min_stock_level' => 8,
                'max_stock_level' => 30,
                'category_id' => $atk->id,
                'status' => 'in_stock'
            ]
        ];

        foreach ($products as $productData) {
            $product = Product::create($productData);

            if ($product->stock_quantity > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => $product->stock_quantity,
                    'previous_stock' => 0,
                    'current_stock' => $product->stock_quantity,
                    'reference' => 'initial_stock',
                    'notes' => 'Initial stock entry'
                ]);
            }
        }
    }
}
