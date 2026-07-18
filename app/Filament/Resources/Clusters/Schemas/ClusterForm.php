<?php

namespace App\Filament\Resources\Clusters\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ClusterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->placeholder(__('Example: Cluster A - Budi')),
                Select::make('officer_id')
                    ->label(__('Officer PIC'))
                    ->relationship('officer', 'name', fn ($query) => $query->where('role', Role::FieldOfficer))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText(__('Changing the PIC here moves all cluster customers to the new officer')),
                Textarea::make('description')
                    ->label(__('Description'))
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),
            ]);
    }
}
