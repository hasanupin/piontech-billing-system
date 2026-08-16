<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Filament\Widgets\Concerns\ReadsMonthlyBillingFilters;
use App\Services\BillingService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Kartu angka di atas tabel Tagihan Bulanan: jumlah lunas/tidak, nominal
 * belum/sudah bayar, yang sudah disetor, dan total komisi periode terpilih.
 */
class MonthlyBillingSummary extends StatsOverviewWidget
{
    use ReadsMonthlyBillingFilters;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 3;

    protected function getStats(): array
    {
        $progress = $this->progress();

        $stats = [
            Stat::make(__('Paid / Unpaid'), $progress['paid'].' / '.$progress['unpaid'])
                // Description wajib ada: warna kartu hanya ter-render lewatnya.
                ->description(__(':count billed customers', ['count' => $progress['billed']]))
                ->icon('heroicon-o-check-badge')
                ->color('success'),
            Stat::make(__('Unpaid / Paid'), BillingStatsOverview::rupiah($progress['outstanding']))
                ->description(__(':amount paid', ['amount' => BillingStatsOverview::rupiah($progress['paid_amount'])]))
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
            Stat::make(__('Deposited'), BillingStatsOverview::rupiah($progress['deposited']))
                ->description(__('Cash handed to admin'))
                ->icon('heroicon-o-banknotes')
                ->color('warning'),
        ];

        // Komisi mengikuti hak akses halaman Komisi (CEO + admin) dan sengaja
        // mengabaikan filter cluster — penerima komisi tidak terikat cluster.
        if (auth()->user()?->isRole(Role::SuperAdmin, Role::Admin)) {
            $stats[] = Stat::make(__('Commission'), BillingStatsOverview::rupiah(
                app(BillingService::class)->commissionTotal($this->periodStart()),
            ))
                ->description($this->periodStart()->translatedFormat('F Y'))
                ->icon('heroicon-o-receipt-percent')
                ->color('info');
        }

        return $stats;
    }
}
