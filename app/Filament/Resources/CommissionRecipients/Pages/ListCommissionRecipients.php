<?php

namespace App\Filament\Resources\CommissionRecipients\Pages;

use App\Filament\Resources\CommissionRecipients\CommissionRecipientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommissionRecipients extends ListRecords
{
    protected static string $resource = CommissionRecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
