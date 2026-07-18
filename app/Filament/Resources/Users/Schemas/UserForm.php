<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Full Name'))
                    ->maxLength(100)
                    ->required(),
                TextInput::make('username')
                    ->label(__('Username'))
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->maxLength(150)
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('phone')
                    ->label(__('Phone Number'))
                    ->tel()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label(__('Password'))
                    ->password()
                    ->revealable()
                    // Wajib saat create; saat edit kosongkan untuk mempertahankan password lama.
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
                Select::make('role')
                    ->label(__('Role'))
                    ->options(Role::class)
                    ->default(Role::FieldOfficer)
                    ->native(false)
                    ->required(),
                Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),
            ]);
    }
}
