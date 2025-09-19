<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Elektronik',
                'slug' => 'electronics',
                'description' => 'Segala benda elektronik termasuk perangkat aksesorisnya',
                'color' => '#3b82f6'
            ],
            [
                'name' => 'Alat Tulis Kerja',
                'slug' => 'atk',
                'description' => 'Alat tulis dan perlengkapan kerja lainnya',
                'color' => '#10b981'
            ],
            [
                'name' => 'Furnitur',
                'slug' => 'furnitur',
                'description' => 'Furnitur untuk sekolah',
                'color' => '#f59e0b'
            ]
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
