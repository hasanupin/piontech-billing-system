<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = auth()->id();
        // Normalisasi periode ke awal bulan agar unique(customer_id, period) konsisten.
        $data['period'] = Carbon::parse($data['period'])->startOfMonth();

        return $data;
    }
}
