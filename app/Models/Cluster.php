<?php

namespace App\Models;

use Database\Factories\ClusterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'name',
    'officer_id',
    'description',
    'is_active',
])]
class Cluster extends Model
{
    /** @use HasFactory<ClusterFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id')->withTrashed();
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** Transaksi seluruh pelanggan cluster ini — dasar Laporan Per Cluster. */
    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(Transaction::class, Customer::class);
    }
}
