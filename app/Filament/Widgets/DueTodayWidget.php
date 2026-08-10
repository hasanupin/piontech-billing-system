<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionStatus;
use App\Models\Customer;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pelanggan jatuh tempo hari ini yang belum bayar, per cluster (TASK-10).
 * Selalu bulan berjalan — "hari ini" tidak terpengaruh filter periode.
 */
class DueTodayWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Due Today'))
            ->query(
                Customer::query()
                    ->dueToday()
                    ->whereDoesntHave('transactions', fn (Builder $q) => $q
                        ->forPeriod(now()->startOfMonth())
                        ->where('status', TransactionStatus::Paid)),
            )
            ->defaultGroup(
                Group::make('cluster.name')->label(__('Cluster')),
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name')),
                TextColumn::make('cluster.name')
                    ->label(__('Cluster')),
                TextColumn::make('billing_amount')
                    ->label(__('Amount'))
                    ->fontFamily(FontFamily::Mono)
                    ->state(fn (Customer $record): string => BillingStatsOverview::rupiah((float) $record->billing_amount)),
                TextColumn::make('whatsapp_number')
                    ->label(__('WhatsApp'))
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->paginated(false);
    }
}
