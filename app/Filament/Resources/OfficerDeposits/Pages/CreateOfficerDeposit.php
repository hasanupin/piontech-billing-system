<?php

namespace App\Filament\Resources\OfficerDeposits\Pages;

use App\Enums\Role;
use App\Filament\Resources\OfficerDeposits\OfficerDepositResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOfficerDeposit extends CreateRecord
{
    protected static string $resource = OfficerDepositResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Field officer: officer_id di-disable (tidak ter-submit) — kunci ke diri sendiri.
        if (auth()->user()?->isRole(Role::FieldOfficer)) {
            $data['officer_id'] = auth()->id();
        }

        return $data;
    }
}
