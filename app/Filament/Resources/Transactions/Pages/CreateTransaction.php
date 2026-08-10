<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Pages\MonthlyBilling;
use App\Filament\Resources\Transactions\TransactionResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    /**
     * Diisi dari ?customer_id=... saat mount, lalu ikut tersimpan di state
     * Livewire. Tidak boleh dibaca ulang lewat request()->query() di luar
     * mount(): request berikutnya adalah POST ke /livewire/update yang tidak
     * membawa query string, sehingga nilainya akan hilang.
     */
    public ?string $customerId = null;

    public function mount(): void
    {
        $this->customerId = request()->query('customer_id');

        parent::mount();
    }

    /**
     * Datang dari "Catat Pembayaran" = mencatat satu tagihan spesifik,
     * bukan entri massal — "Buat & buat lainnya" tidak relevan di sana.
     */
    public function canCreateAnother(): bool
    {
        return blank($this->customerId);
    }

    protected function getRedirectUrl(): string
    {
        // Kembali ke halaman asal alur kerjanya; tanpa customer_id biarkan
        // perilaku bawaan Filament (lanjut ke halaman edit).
        return filled($this->customerId)
            ? MonthlyBilling::getUrl()
            : parent::getRedirectUrl();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = auth()->id();
        // Normalisasi periode ke awal bulan agar unique(customer_id, period) konsisten.
        $data['period'] = Carbon::parse($data['period'])->startOfMonth();

        return $data;
    }
}
