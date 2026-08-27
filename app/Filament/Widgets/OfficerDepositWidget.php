<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Support\Enums\FontFamily;
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

    protected int|string|array $columnSpan = 'full';

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
                // Keempat angka datang dari satu panggilan officerProgress()
                // (di-memo per petugas+periode di service).
                TextColumn::make('must_collect')
                    ->label(__('Must Collect'))
                    ->fontFamily(FontFamily::Mono)
                    ->state(fn (User $record): string => BillingStatsOverview::rupiah(
                        $this->progressOf($record)['target'],
                    )),
                TextColumn::make('cash_total')
                    ->label(__('Collected'))
                    ->fontFamily(FontFamily::Mono)
                    ->state(fn (User $record): string => BillingStatsOverview::rupiah(
                        $this->progressOf($record)['collected'],
                    )),
                TextColumn::make('uncollected')
                    ->label(__('Not Collected Yet'))
                    ->fontFamily(FontFamily::Mono)
                    ->state(fn (User $record): float => $this->progressOf($record)['uncollected'])
                    ->formatStateUsing(fn (float $state): string => BillingStatsOverview::rupiah($state))
                    ->color(fn (float $state): string => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('deposited')
                    ->label(__('Total Deposited'))
                    ->fontFamily(FontFamily::Mono)
                    ->state(fn (User $record): string => BillingStatsOverview::rupiah(
                        $this->progressOf($record)['deposited'],
                    )),
                TextColumn::make('remaining')
                    ->label(__('Remaining'))
                    ->fontFamily(FontFamily::Mono)
                    ->state(fn (User $record): float => $this->progressOf($record)['remaining'])
                    ->formatStateUsing(fn (float $state): string => BillingStatsOverview::rupiah($state))
                    // Merah bila masih ada kekurangan setor.
                    ->color(fn (float $state): string => $state > 0 ? 'danger' : 'success')
                    ->weight('bold'),
            ])
            ->paginated(false);
    }

    /** @return array{target: float, collected: float, uncollected: float, deposited: float, remaining: float} */
    private function progressOf(User $officer): array
    {
        return app(BillingService::class)->officerProgress($officer->id, $this->periodStart());
    }

    public function periodStart(): Carbon
    {
        return Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();
    }
}
