<?php

namespace App\Filament\Resources\Packages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->placeholder(__('Example: Package 110')),
                TextInput::make('default_price')
                    ->label(__('Default Price'))
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->helperText(__('Reference price — can be overridden when recording a transaction')),
                TextInput::make('speed_mbps')
                    ->label(__('Speed (Mbps)'))
                    ->numeric(),
                Textarea::make('description')
                    ->label(__('Description'))
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),
            ]);
    }
}
