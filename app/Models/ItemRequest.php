<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemRequest extends Model
{
    use HasFactory;
    protected $table = 'item_request';
    protected $fillable = [
        'request_number',
        'user_id',
        'note',
        'status',
        'approved_by',
        'approved_at',
        'admin_note'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    // Generate unique request number
    public static function generateRequestNumber()
    {
        $date = now()->format('Ymd');
        $lastRequest = self::where('request_number', 'like', "REQ-{$date}-%")
            ->orderBy('request_number', 'desc')
            ->first();

        if ($lastRequest) {
            $lastNumber = (int) substr($lastRequest->request_number, -3);
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        return "REQ-{$date}-{$newNumber}";
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ItemRequestDetail::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ItemRequestLog::class);
    }

    // Computed properties
    public function getTotalItemsAttribute()
    {
        return $this->details->sum('requested_quantity');
    }

    public function getTotalApprovedAttribute()
    {
        return $this->details->sum('approved_quantity');
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'draft' => 'bg-gray-100 text-gray-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'approved' => 'bg-blue-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            'partially_approved' => 'bg-cyan-100 text-cyan-800',
            'completed' => 'bg-blue-100 text-blue-800',
        ];

        return $badges[$this->status] ?? 'bg-black text-black';
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
