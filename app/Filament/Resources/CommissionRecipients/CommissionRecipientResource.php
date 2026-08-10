<?php

namespace App\Filament\Resources\CommissionRecipients;

use App\Enums\Role;
use App\Filament\Resources\CommissionRecipients\Pages\CreateCommissionRecipient;
use App\Filament\Resources\CommissionRecipients\Pages\EditCommissionRecipient;
use App\Filament\Resources\CommissionRecipients\Pages\ListCommissionRecipients;
use App\Filament\Resources\CommissionRecipients\Schemas\CommissionRecipientForm;
use App\Filament\Resources\CommissionRecipients\Tables\CommissionRecipientsTable;
use App\Models\CommissionRecipient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CommissionRecipientResource extends Resource
{
    protected static ?string $model = CommissionRecipient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Commission Recipient');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Commission Recipients');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Master');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isRole(Role::SuperAdmin, Role::Admin) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return CommissionRecipientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommissionRecipientsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionRecipients::route('/'),
            'create' => CreateCommissionRecipient::route('/create'),
            'edit' => EditCommissionRecipient::route('/{record}/edit'),
        ];
    }
}
