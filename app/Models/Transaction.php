<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use Carbon\Carbon;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'officer_id',
    'period',
    'billed_amount',
    'paid_amount',
    'status',
    'payment_method',
    'recorded_by',
    'paid_at',
    'notes',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, HasUlids;

    protected static function booted(): void
    {
        static::saving(function (self $t) {
            // Auto status berdasarkan nominal bayar vs tagihan.
            $t->status = match (true) {
                $t->paid_amount >= $t->billed_amount => TransactionStatus::Paid,
                $t->paid_amount > 0 => TransactionStatus::Partial,
                default => TransactionStatus::Unpaid,
            };

            // Transfer = langsung ke rekening Pion Tech, tanpa perantara petugas.
            if ($t->payment_method === PaymentMethod::Transfer) {
                $t->officer_id = null;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'date',
            'billed_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => TransactionStatus::class,
            'payment_method' => PaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    public function scopeCash(Builder $query): Builder
    {
        return $query->where('payment_method', PaymentMethod::Cash);
    }

    public function scopeForPeriod(Builder $query, Carbon|string $date): Builder
    {
        return $query->whereDate('period', Carbon::parse($date)->startOfMonth());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id')->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }
}
