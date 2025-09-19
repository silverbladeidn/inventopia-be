<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'sku',
        'price',
        'cost_price',
        'stock_quantity',
        'min_stock_level',
        'max_stock_level',
        'category_id',
        'images',
        'slug',
        'is_active',
        'status'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'min_stock_level' => 'integer',
        'max_stock_level' => 'integer',
    ];

    protected $appends = ['image_url'];

    /**
     * Accessor untuk image_url
     */
    public function getImageUrlAttribute()
    {
        return $this->images ? asset('storage/' . $this->images) : null;
    }


    /**
     * Relationship dengan category
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relationship dengan stock movements
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    /**
     * Update stock status berdasarkan quantity
     */
    public function updateStockStatus()
    {
        if ($this->stock_quantity === 0) {
            $this->status = 'out_of_stock';
        } elseif ($this->stock_quantity <= $this->min_stock_level) {
            $this->status = 'low_stock';
        } else {
            $this->status = 'in_stock';
        }
    }

    /**
     * Scope untuk filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk filter by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope untuk search
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
                ->orWhere('sku', 'like', "%{$searchTerm}%")
                ->orWhere('description', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Boot method untuk event handling
     */
    protected static function boot()
    {
        parent::boot();

        // Auto generate slug ketika creating
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = \Illuminate\Support\Str::slug($product->name);
            }
        });

        // Auto update status ketika saving
        static::saving(function ($product) {
            $product->updateStockStatus();
        });

        // Hapus file gambar ketika deleting
        static::deleting(function ($product) {
            if ($product->images && Storage::disk('public')->exists($product->images)) {
                Storage::disk('public')->delete($product->images);
            }
        });
    }
}
