<?php

namespace App\Models;

use App\Enums\RecipientType;
use Database\Factories\CommissionRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Penerima komisi atas referal pelanggan.
 *
 * Tipe Pelanggan = MIRROR: nama/alamat/WA tidak disalin, selalu dibaca dari
 * record pelanggan yang ditunjuk (data pelanggan berubah → penerima ikut).
 * Tipe Non Pelanggan memakai kolomnya sendiri. Baca lewat display_* saja.
 */
#[Fillable([
    'type',
    'customer_id',
    'name',
    'address',
    'whatsapp_number',
    'commission_percent',
    'is_active',
])]
class CommissionRecipient extends Model
{
    /** @use HasFactory<CommissionRecipientFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RecipientType::class,
            'commission_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * withoutGlobalScopes(): Customer punya global scope cluster untuk petugas
     * (Customer::booted()). Tanpa ini, opsi Referal di form pelanggan berlabel
     * kosong saat petugas membukanya karena penerimanya pelanggan cluster lain.
     * Yang dibuka di sini cuma kontak satu penerima komisi, bukan listing pelanggan.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withoutGlobalScopes();
    }

    public function referredCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'referral_id');
    }

    /**
     * Transaksi milik pelanggan-pelanggan yang direferalkan penerima ini —
     * dasar hitungan komisi. Pelanggan yang di-soft-delete otomatis dilewati
     * Laravel pada relasi through.
     */
    public function referredTransactions(): HasManyThrough
    {
        return $this->hasManyThrough(Transaction::class, Customer::class, 'referral_id', 'customer_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Komisi periode terpilih. paid_base hanya terisi pada query dari
     * BillingService::commissionQuery(); di luar itu hasilnya 0.
     */
    protected function commissionAmount(): Attribute
    {
        return Attribute::get(fn (): float => round(
            (float) ($this->paid_base ?? 0) * (float) $this->commission_percent / 100,
            2,
        ));
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->mirror('name'));
    }

    protected function displayAddress(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->mirror('address'));
    }

    protected function displayWhatsapp(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->mirror('whatsapp_number'));
    }

    /** Aturan mirror hanya ada di sini. */
    private function mirror(string $attribute): ?string
    {
        return $this->type === RecipientType::Customer
            ? $this->customer?->{$attribute}
            : $this->{$attribute};
    }
}
