<?php

namespace App\Filament\Resources\OfficerDeposits;

use App\Enums\Role;
use App\Filament\Resources\OfficerDeposits\Pages\CreateOfficerDeposit;
use App\Filament\Resources\OfficerDeposits\Pages\EditOfficerDeposit;
use App\Filament\Resources\OfficerDeposits\Pages\ListOfficerDeposits;
use App\Filament\Resources\OfficerDeposits\Schemas\OfficerDepositForm;
use App\Filament\Resources\OfficerDeposits\Tables\OfficerDepositsTable;
use App\Models\OfficerDeposit;
use App\Services\ScopeService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfficerDepositResource extends Resource
{
    protected static ?string $model = OfficerDeposit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('Officer Deposit');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Officer Deposits');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Billing');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isRole(Role::SuperAdmin, Role::Admin, Role::FieldOfficer) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return app(ScopeService::class)->scopeDepositsForUser(
            parent::getEloquentQuery(),
            auth()->user(),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return OfficerDepositForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OfficerDepositsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOfficerDeposits::route('/'),
            'create' => CreateOfficerDeposit::route('/create'),
            'edit' => EditOfficerDeposit::route('/{record}/edit'),
        ];
    }
}
