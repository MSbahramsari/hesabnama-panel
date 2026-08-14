<?php

namespace App\Models;

use App\Enums\Plan;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'plan', 'permissions', 'license_expires_at', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = ['role' => 'member', 'plan' => 'starter', 'is_active' => true];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function goods(): HasMany
    {
        return $this->hasMany(Good::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function taxpayerProfile(): HasOne
    {
        return $this->hasOne(TaxpayerProfile::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isAdmin() || in_array($permission, $this->permissions ?? [], true);
    }

    public function hasActiveLicense(): bool
    {
        return $this->is_active && ($this->isAdmin() || $this->license_expires_at?->isFuture());
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'plan' => Plan::class,
            'permissions' => 'array',
            'license_expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
