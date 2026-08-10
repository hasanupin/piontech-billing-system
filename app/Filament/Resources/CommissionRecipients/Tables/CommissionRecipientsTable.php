<?php

namespace App\Filament\Resources\CommissionRecipients\Tables;

use App\Enums\RecipientType;
use App\Models\CommissionRecipient;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommissionRecipientsTable
{
    /**
     * Nama penerima. Tipe Pelanggan tidak punya nama di kolomnya (mirror), jadi
     * tampilan & pencarian harus ikut menengok ke tabel customers.
     * Dipakai ulang halaman Komisi — jangan disalin, gampang melenceng.
     */
    public static function nameColumn(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('Name'))
            ->state(fn (CommissionRecipient $record): ?string => $record->display_name)
            ->searchable(query: fn (Builder $query, string $search): Builder => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $q) => $q->where('name', 'like', "%{$search}%")));
    }

    /** WhatsApp penerima (mirror) sebagai link wa.me. */
    public static function whatsappColumn(): TextColumn
    {
        return TextColumn::make('whatsapp_number')
            ->label(__('WhatsApp'))
            ->state(fn (CommissionRecipient $record): ?string => $record->display_whatsapp)
            ->url(fn (CommissionRecipient $record): ?string => $record->display_whatsapp
                ? 'https://wa.me/'.ltrim($record->display_whatsapp, '+')
                : null)
            ->openUrlInNewTab();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                self::nameColumn(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge(),
                TextColumn::make('address')
                    ->label(__('Address'))
                    ->state(fn (CommissionRecipient $record): ?string => $record->display_address)
                    ->toggleable(),
                self::whatsappColumn(),
                TextColumn::make('commission_percent')
                    ->label(__('Commission Percentage'))
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('referred_customers_count')
                    ->label(__('Referred Customers'))
                    ->counts('referredCustomers')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(RecipientType::class),
                TernaryFilter::make('is_active')
                    ->label(__('Active')),
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
