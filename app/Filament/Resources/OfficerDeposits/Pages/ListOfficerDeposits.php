<?php

namespace App\Filament\Resources\OfficerDeposits\Pages;

use App\Filament\Resources\OfficerDeposits\OfficerDepositResource;
use App\Filament\Resources\OfficerDeposits\Widgets\OfficerDepositSummary;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOfficerDeposits extends ListRecords
{
    protected static string $resource = OfficerDepositResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OfficerDepositSummary::class,
        ];
    }
}
