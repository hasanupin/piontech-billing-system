<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/**
 * Ringkasan setoran per petugas — setara header TITIP + KEKURANGAN Excel (TASK-10).
 */
class OfficerDepositWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->heading(__('Officer Deposits'))
            ->query(
                User::query()
                    ->where('role', Role::FieldOfficer)
                    // Petugas login hanya melihat baris dirinya sendiri.
                    ->when($user?->isRole(Role::FieldOfficer), fn ($q) => $q->whereKey($user->id)),
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('Officer')),
                TextColumn::make('cash_total')
                    ->label(__('Cash Collected'))
                    ->state(fn (User $record): string => BillingStatsOverview::rupiah(
                        (float) Transaction::where('officer_id', $record->id)
                            ->forPeriod($this->periodStart())->cash()->sum('paid_amount'),
                    )),
                TextColumn::make('deposited')
                    ->label(__('Total Deposited'))
                    ->state(fn (User $record): string => BillingStatsOverview::rupiah(
                        (float) OfficerDeposit::where('officer_id', $record->id)
                            ->whereDate('period', $this->periodStart())->sum('amount'),
                    )),
                TextColumn::make('remaining')
                    ->label(__('Remaining'))
                    ->state(fn (User $record): float => app(BillingService::class)
                        ->officerRemainingBalance($record->id, $this->periodStart()))
                    ->formatStateUsing(fn (float $state): string => BillingStatsOverview::rupiah($state))
                    // Merah bila masih ada kekurangan setor.
                    ->color(fn (float $state): string => $state > 0 ? 'danger' : 'success')
                    ->weight('bold'),
            ])
            ->paginated(false);
    }

    public function periodStart(): Carbon
    {
        return Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();
    }
}
