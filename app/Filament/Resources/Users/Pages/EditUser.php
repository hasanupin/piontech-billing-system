<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        // Jangan biarkan user menghapus akunnya sendiri.
        $isSelf = fn (): bool => $this->getRecord()->getKey() === auth()->id();

        return [
            DeleteAction::make()->hidden($isSelf),
            ForceDeleteAction::make()->hidden($isSelf),
            RestoreAction::make(),
        ];
    }
}
