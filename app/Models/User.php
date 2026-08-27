<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'username',
    'email',
    'phone',
    'password',
    'role',
    'is_active',
    'commission_per_customer',
    'created_by',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'commission_per_customer' => 'decimal:2',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Petugas hanya lewat panel mobile /petugas; role lain hanya /admin.
        return $this->is_active && match ($panel->getId()) {
            'field' => $this->isRole(Role::FieldOfficer),
            default => ! $this->isRole(Role::FieldOfficer),
        };
    }

    // --- Relationships ---

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function clusters(): HasMany
    {
        return $this->hasMany(Cluster::class, 'officer_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'officer_id');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(OfficerDeposit::class, 'officer_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    /**
     * Pelanggan yang ditangani petugas ini lewat cluster yang dipegangnya —
     * dasar seluruh hitungan komisi (BillingService::commissionQuery()).
     */
    public function clusterCustomers(): HasManyThrough
    {
        return $this->hasManyThrough(Customer::class, Cluster::class, 'officer_id', 'cluster_id');
    }

    // --- Commission ---

    /**
     * Komisi periode berjalan = jumlah pelanggan lunas x tarif per pelanggan.
     * `paid_customers` hanya terisi bila query-nya datang dari
     * BillingService::commissionQuery(); di tempat lain hasilnya 0.
     */
    protected function commissionAmount(): Attribute
    {
        return Attribute::get(fn (): float => round(
            (int) ($this->paid_customers ?? 0) * (float) $this->commission_per_customer,
            2,
        ));
    }

    /** Komisi yang belum jadi hak karena pelanggannya belum bayar. */
    protected function estimatedCommissionAmount(): Attribute
    {
        return Attribute::get(fn (): float => round(
            (int) ($this->estimated_customers ?? 0) * (float) $this->commission_per_customer,
            2,
        ));
    }

    // --- Role Helpers ---

    public function isRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }
}
