<?php

namespace App\Filament\Resources\OfficerDeposits\Tables;

use App\Models\OfficerDeposit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OfficerDepositsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('deposited_at', 'desc')
            ->columns([
                TextColumn::make('officer.name')
                    ->label(__('Officer'))
                    ->searchable(),
                TextColumn::make('period')
                    ->label(__('Period'))
                    ->date('F Y')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('must_collect')
                    ->label(__('Must Collect'))
                    ->state(fn (OfficerDeposit $record): float => $record->mustCollect())
                    ->money('IDR'),
                TextColumn::make('uncollected')
                    ->label(__('Not Collected Yet'))
                    ->state(fn (OfficerDeposit $record): float => $record->uncollected())
                    ->money('IDR')
                    ->color(fn (float $state): string => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('receiver.name')
                    ->label(__('Received By')),
                TextColumn::make('deposited_at')
                    ->label(__('Deposited At'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
