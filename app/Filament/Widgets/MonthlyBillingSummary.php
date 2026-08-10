<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsMonthlyBillingFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Kartu angka di atas tabel Tagihan Bulanan: berapa lunas, berapa belum,
 * dan berapa yang sudah disetor petugas pada periode terpilih.
 */
class MonthlyBillingSummary extends StatsOverviewWidget
{
    use ReadsMonthlyBillingFilters;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 2;

    protected function getStats(): array
    {
        $progress = $this->progress();

        return [
            Stat::make(__('Paid'), $progress['paid'].' / '.$progress['billed'])
                // Description wajib ada: warna kartu hanya ter-render lewatnya.
                ->description(BillingStatsOverview::rupiah($progress['paid_amount']))
                ->icon('heroicon-o-check-badge')
                ->color('success'),
            Stat::make(__('Unpaid'), (string) $progress['unpaid'])
                ->description(BillingStatsOverview::rupiah($progress['outstanding']))
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
            Stat::make(__('Deposited'), BillingStatsOverview::rupiah($progress['deposited']))
                ->description(__('Cash handed to admin'))
                ->icon('heroicon-o-banknotes')
                ->color('warning'),
        ];
    }
}
