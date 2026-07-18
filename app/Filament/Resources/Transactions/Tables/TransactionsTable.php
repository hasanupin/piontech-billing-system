<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable(),
                TextColumn::make('customer.cluster.name')
                    ->label(__('Cluster')),
                TextColumn::make('period')
                    ->label(__('Period'))
                    ->date('F Y')
                    ->sortable(),
                TextColumn::make('billed_amount')
                    ->label(__('Billed Amount'))
                    ->money('IDR'),
                TextColumn::make('paid_amount')
                    ->label(__('Paid Amount'))
                    ->money('IDR'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('payment_method')
                    ->label(__('Payment Method'))
                    ->badge(),
                TextColumn::make('officer.name')
                    ->label(__('Officer')),
                TextColumn::make('paid_at')
                    ->label(__('Paid At'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->filters([
                // Quick filter — tombol toggle di atas table.
                Filter::make('lunas')
                    ->label(__('LUNAS'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('status', TransactionStatus::Paid)),
                Filter::make('tunai')
                    ->label(__('TUNAI'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('payment_method', PaymentMethod::Cash)),
                Filter::make('transfer')
                    ->label(__('TRANSFER'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('payment_method', PaymentMethod::Transfer)),
                // Periode bulan-tahun.
                SelectFilter::make('period')
                    ->label(__('Period'))
                    ->options(self::periodOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereDate('period', Carbon::parse($data['value'].'-01')->startOfMonth())
                        : $query),
                // Rentang tanggal bayar.
                Filter::make('paid_between')
                    ->label(__('Paid Date Range'))
                    ->schema([
                        DatePicker::make('from')->label(__('From')),
                        DatePicker::make('until')->label(__('Until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('paid_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('paid_at', '<=', $date))),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(TransactionStatus::class),
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

    /**
     * Opsi periode: 12 bulan terakhir (Y-m => "Juli 2026").
     *
     * @return array<string, string>
     */
    private static function periodOptions(): array
    {
        $options = [];
        $cursor = now()->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $options[$cursor->format('Y-m')] = $cursor->translatedFormat('F Y');
            $cursor->subMonth();
        }

        return $options;
    }
}
