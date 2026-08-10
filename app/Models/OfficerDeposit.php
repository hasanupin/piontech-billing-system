<?php

namespace App\Models;

use App\Services\BillingService;
use Database\Factories\OfficerDepositFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'officer_id',
    'period',
    'amount',
    'received_by',
    'deposited_at',
    'notes',
])]
class OfficerDeposit extends Model
{
    /** @use HasFactory<OfficerDepositFactory> */
    use HasFactory, HasUlids;

    protected static function booted(): void
    {
        // Angka progres petugas di-memo per-request di BillingService; setoran
        // yang berubah di request yang sama harus membuatnya dihitung ulang.
        $forget = fn () => app(BillingService::class)->forgetOfficerProgress();

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'date',
            'amount' => 'decimal:2',
            'deposited_at' => 'datetime',
        ];
    }

    /**
     * Yang harus ditarik petugas ini pada periode baris tsb — dipakai kolom
     * tabel & export supaya angkanya satu sumber dengan panel per petugas.
     */
    public function mustCollect(): float
    {
        return app(BillingService::class)->officerProgress($this->officer_id, $this->period)['target'];
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id')->withTrashed();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }
}
