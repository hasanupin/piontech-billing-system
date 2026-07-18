<?php

namespace App\Filament\Resources\OfficerDeposits\Pages;

use App\Filament\Resources\OfficerDeposits\OfficerDepositResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOfficerDeposit extends EditRecord
{
    protected static string $resource = OfficerDepositResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
