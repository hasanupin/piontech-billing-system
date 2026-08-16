<?php

namespace App\Filament\Resources\CommissionRecipients\RelationManagers;

use App\Models\CommissionRecipient;
use App\Models\Customer;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReferredCustomersRelationManager extends RelationManager
{
    protected static string $relationship = 'referredCustomers';

    // Associate/Dissociate menebak relasi balik dari nama model induk (`commissionRecipient`),
    // padahal di Customer namanya `referral`.
    protected static ?string $inverseRelationship = 'referral';

    /**
     * Calon referal: pelanggan yang belum punya penerima komisi — jangan diam-diam
     * mencuri referal milik penerima lain — dan bukan pelanggan penerima ini sendiri.
     */
    public static function associateOptions(CommissionRecipient $recipient): Builder
    {
        return Customer::query()
            ->whereNull('referral_id')
            ->when($recipient->customer_id, fn (Builder $query, string $customerId) => $query
                ->whereKeyNot($customerId));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Referred Customers'))
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable(),
                TextColumn::make('cluster.name')->label(__('Cluster')),
                TextColumn::make('package.name')->label(__('Package')),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label(__('Add Referral'))
                    ->modalHeading(__('Add Referral'))
                    ->recordSelectSearchColumns(['name'])
                    ->recordSelectOptionsQuery(fn (): Builder => self::associateOptions($this->getOwnerRecord())),
            ])
            // Konfirmasi bawaan DissociateAction; hanya melepas referral_id, pelanggan tetap ada.
            ->recordActions([
                DissociateAction::make()
                    ->label(__('Remove Referral'))
                    ->modalHeading(fn (Customer $record): string => __('Remove Referral').': '.$record->name)
                    ->modalDescription(__('The customer record is not deleted, only unlinked from this commission recipient.')),
            ]);
    }
}
