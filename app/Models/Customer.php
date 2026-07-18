<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\Role;
use App\Services\ScopeService;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'full_name',
    'whatsapp_number',
    'cluster_id',
    'package_id',
    'address',
    'maps_url',
    'house_photo_url',
    'billing_day',
    'status',
    'suspended_at',
    'registered_at',
    'notes',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected static function booted(): void
    {
        // Scoping cluster untuk field officer terpusat di ScopeService.
        static::addGlobalScope('cluster_scope', function (Builder $query) {
            $user = auth()->user();

            if ($user?->isRole(Role::FieldOfficer)) {
                app(ScopeService::class)->scopeCustomersForUser($query, $user);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'billing_day' => 'integer',
            'suspended_at' => 'date',
            'registered_at' => 'date',
        ];
    }

    /** Pelanggan yang masih ditagih: aktif + isolir (terminated tidak). */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CustomerStatus::Active,
            CustomerStatus::Suspended,
        ]);
    }

    /** Pelanggan yang jatuh tempo hari ini. */
    public function scopeDueToday(Builder $query): Builder
    {
        return $query->billable()->where('billing_day', now()->day);
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
