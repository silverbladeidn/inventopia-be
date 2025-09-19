<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Carbon\Carbon;

class Session extends Model
{
    protected $table = 'sessions';
    public $timestamps = false;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
    ];

    /**
     * Casting atribut.
     */
    protected function casts(): array
    {
        return [
            'last_activity' => 'datetime',
            'user_id' => 'integer',
        ];
    }

    /**
     * Relasi ke user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope untuk session aktif (dalam 2 jam terakhir)
     */
    public function scopeActive($query)
    {
        return $query->where('last_activity', '>=', now()->subHours(2)->timestamp);
    }

    /**
     * Scope untuk session user tertentu
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Accessor untuk mendapatkan waktu last activity yang readable
     */
    public function getLastActivityHumanAttribute()
    {
        return Carbon::createFromTimestamp($this->last_activity)->diffForHumans();
    }

    /**
     * Accessor untuk mendapatkan informasi browser dari user agent
     */
    public function getBrowserInfoAttribute()
    {
        $userAgent = $this->user_agent;

        // Deteksi browser sederhana
        if (strpos($userAgent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            return 'Edge';
        }

        return 'Unknown Browser';
    }

    /**
     * Accessor untuk mendapatkan informasi OS dari user agent
     */
    public function getOsInfoAttribute()
    {
        $userAgent = $this->user_agent;

        if (strpos($userAgent, 'Windows') !== false) {
            return 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            return 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            return 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            return 'Android';
        } elseif (strpos($userAgent, 'iOS') !== false) {
            return 'iOS';
        }

        return 'Unknown OS';
    }

    /**
     * Method untuk cek apakah session masih aktif
     */
    public function isActive(): bool
    {
        return $this->last_activity >= now()->subHours(2)->timestamp;
    }
}
