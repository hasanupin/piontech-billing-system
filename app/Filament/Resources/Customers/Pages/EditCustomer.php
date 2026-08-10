<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Services\ScopeService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Cegah petugas memindahkan pelanggan ke cluster di luar scope-nya.
        app(ScopeService::class)->authorizeCustomerCluster(auth()->user(), $data['cluster_id'] ?? null);

        return $data;
    }
}
