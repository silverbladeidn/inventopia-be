<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'is_blocked',
        'role_id', // ⬅️ Tambah ini untuk foreign key (1 user = 1 role)
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
        ];
    }

    /**
     * Relasi ke Role (1 user = 1 role)
     * Gunakan belongsTo karena user punya 1 role
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Helper cek role berdasarkan nama
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role?->name === $roleName;
    }

    /**
     * Helper cek permission melalui role
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->role?->permissions->contains('name', $permissionName) ?? false;
    }

    /**
     * Mendapatkan semua permissions user melalui role-nya
     */
    public function getAllPermissions()
    {
        return $this->role?->permissions ?? collect([]);
    }

    /**
     * Cek apakah user aktif (tidak di-block)
     */
    public function isActive(): bool
    {
        return !$this->is_blocked;
    }

    /**
     * Scope untuk filter user berdasarkan role
     */
    public function scopeWithRole($query, string $roleName)
    {
        return $query->whereHas('role', function ($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }

    /**
     * Scope untuk user yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_blocked', false);
    }
}
