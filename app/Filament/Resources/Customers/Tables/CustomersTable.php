<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Enums\CustomerStatus;
use App\Enums\Role;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('package.name')
                    ->label(__('Package')),
                TextColumn::make('billing_amount')
                    ->label(__('Billing Amount'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('address')
                    ->label(__('Address')),
                TextColumn::make('cluster.officer.name')
                    ->label(__('Officer')),
                TextColumn::make('billing_day')
                    ->label(__('Billing Day'))
                    ->sortable(),
                TextColumn::make('whatsapp_number')
                    ->label(__('WhatsApp'))
                    ->url(fn (Customer $record): ?string => $record->whatsapp_number
                        ? 'https://wa.me/'.ltrim($record->whatsapp_number, '+')
                        : null)
                    ->openUrlInNewTab(),
                TextColumn::make('maps_url')
                    ->label(__('Maps'))
                    ->formatStateUsing(fn () => '📍 '.__('Open'))
                    ->url(fn (Customer $record): ?string => $record->maps_url)
                    ->openUrlInNewTab(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(CustomerStatus::class),
                SelectFilter::make('cluster_id')
                    ->label(__('Cluster'))
                    ->relationship('cluster', 'name'),
                Filter::make('due_today')
                    ->label(__('Due Today'))
                    ->query(fn (Builder $query): Builder => $query->dueToday()),
            ])
            ->recordActions([
                // ISOLIR = jatuh tempo belum bayar; hanya admin yang boleh ubah status.
                Action::make('suspend')
                    ->label(__('Mark as ISOLIR'))
                    ->color('warning')
                    ->icon('heroicon-o-lock-closed')
                    ->visible(fn (Customer $record): bool => $record->status === CustomerStatus::Active
                        && (auth()->user()?->isRole(Role::SuperAdmin, Role::Admin) ?? false))
                    ->requiresConfirmation()
                    ->action(fn (Customer $record) => $record->update([
                        'status' => CustomerStatus::Suspended,
                        'suspended_at' => now()->toDateString(),
                    ])),
                Action::make('restore_active')
                    ->label(__('Restore Active'))
                    ->color('success')
                    ->icon('heroicon-o-lock-open')
                    ->visible(fn (Customer $record): bool => $record->status === CustomerStatus::Suspended
                        && (auth()->user()?->isRole(Role::SuperAdmin, Role::Admin) ?? false))
                    ->requiresConfirmation()
                    ->action(fn (Customer $record) => $record->update([
                        'status' => CustomerStatus::Active,
                        'suspended_at' => null,
                    ])),
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
