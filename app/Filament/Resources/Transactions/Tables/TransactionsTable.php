<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Models\Cluster;
use App\Services\ScopeService;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
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
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            // Satu kolom — semua filter tersusun ke bawah.
            ->filtersFormColumns(1)
            ->filters([
                // Periode ATAU rentang tanggal bayar — checkbox memilih salah satu.
                // Default: mode periode, bulan berjalan.
                Filter::make('period')
                    ->schema([
                        Checkbox::make('use_range')
                            ->label(__('Use Date Range'))
                            ->live(),
                        Select::make('period')
                            ->label(__('Period'))
                            ->options(self::periodOptions())
                            ->default(now()->format('Y-m'))
                            ->hidden(fn (Get $get): bool => (bool) $get('use_range')),
                        DatePicker::make('from')
                            ->label(__('Start Date'))
                            ->default(now()->startOfMonth())
                            ->visible(fn (Get $get): bool => (bool) $get('use_range')),
                        DatePicker::make('until')
                            ->label(__('End Date'))
                            ->default(now()->endOfMonth())
                            ->visible(fn (Get $get): bool => (bool) $get('use_range')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['use_range'] ?? false) {
                            return $query
                                ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('paid_at', '>=', $date))
                                ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('paid_at', '<=', $date));
                        }

                        return filled($data['period'] ?? null)
                            ? $query->whereDate('period', Carbon::parse($data['period'].'-01')->startOfMonth())
                            : $query;
                    }),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(TransactionStatus::class),
                SelectFilter::make('payment_method')
                    ->label(__('Payment Method'))
                    ->options(PaymentMethod::class),
                SelectFilter::make('officer_id')
                    ->label(__('Officer'))
                    ->relationship('officer', 'name', fn (Builder $query): Builder => $query->where('role', Role::FieldOfficer))
                    ->searchable()
                    ->preload(),
                // Cluster lewat relasi customer; opsi mengikuti scope role user.
                SelectFilter::make('cluster')
                    ->label(__('Cluster'))
                    ->options(fn (): array => app(ScopeService::class)
                        ->scopeClustersForUser(Cluster::query(), auth()->user())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('customer', fn (Builder $q) => $q->where('cluster_id', $data['value']))
                        : $query),
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
