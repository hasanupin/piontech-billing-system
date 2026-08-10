<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Services\ScopeService;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Filter opsi di form hanya UX; payload Livewire tetap harus divalidasi server-side.
        app(ScopeService::class)->authorizeCustomerCluster(auth()->user(), $data['cluster_id'] ?? null);

        return $data;
    }
}
