<?php

namespace App\Filament\Field\Resources\Customers\Pages;

use App\Filament\Field\Resources\Customers\FieldCustomerResource;
use App\Services\ScopeService;
use Filament\Resources\Pages\CreateRecord;

class CreateFieldCustomer extends CreateRecord
{
    protected static string $resource = FieldCustomerResource::class;

    /** Layar sempit: satu tombol simpan saja. */
    public function canCreateAnother(): bool
    {
        return false;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Filter opsi di form hanya UX; payload Livewire tetap divalidasi server-side.
        app(ScopeService::class)->authorizeCustomerCluster(auth()->user(), $data['cluster_id'] ?? null);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return FieldCustomerResource::getUrl('index');
    }
}
