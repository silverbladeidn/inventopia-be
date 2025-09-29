<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemRequestDetail extends Model
{
    use HasFactory;

    protected $table = 'item_request_details';

    protected $fillable = [
        'item_request_id',
        'product_id',
        'requested_quantity',
        'approved_quantity',
        'status',
        'note'
    ];
    protected $appends = ['name'];
    // Relationships
    public function itemRequest(): BelongsTo
    {
        return $this->belongsTo(ItemRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    public function getNameAttribute(): ?string
    {
        return $this->product?->name;
    }

    // Computed properties
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'draft' => 'bg-lime-100 text-lime-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'approved' => 'bg-blue-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'rejected' => 'bg-white text-black',
            'partially_approved' => 'bg-cyan-100 text-cyan-800',
            'completed' => 'bg-blue-100 text-blue-800',
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-500';
    }

    public function getIsPartiallyApprovedAttribute()
    {
        return $this->approved_quantity > 0 && $this->approved_quantity < $this->requested_quantity;
    }

    public function getIsFullyApprovedAttribute()
    {
        return $this->approved_quantity == $this->requested_quantity;
    }
}
