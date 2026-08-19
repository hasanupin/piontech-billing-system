<?php

namespace App\Filament\Field\Resources\Customers\Pages;

use App\Filament\Field\Resources\Customers\FieldCustomerResource;
use App\Services\ScopeService;
use Filament\Resources\Pages\EditRecord;

class EditFieldCustomer extends EditRecord
{
    protected static string $resource = FieldCustomerResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Cegah petugas memindahkan pelanggan ke cluster di luar scope-nya.
        app(ScopeService::class)->authorizeCustomerCluster(auth()->user(), $data['cluster_id'] ?? null);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return FieldCustomerResource::getUrl('index');
    }
}
