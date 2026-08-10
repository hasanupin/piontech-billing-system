<?php

namespace App\Filament\Resources\CommissionRecipients\Pages;

use App\Enums\RecipientType;
use App\Filament\Resources\CommissionRecipients\CommissionRecipientResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCommissionRecipient extends EditRecord
{
    protected static string $resource = CommissionRecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Tipe Pelanggan menyimpan kontak sebagai NULL (mirror), jadi field read-only
     * di form perlu diisi dari pelanggannya supaya tidak tampak kosong saat edit.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record->type !== RecipientType::Customer) {
            return $data;
        }

        $data['name'] = $this->record->display_name;
        $data['address'] = $this->record->display_address;
        $data['whatsapp_number'] = $this->record->display_whatsapp;

        return $data;
    }
}
