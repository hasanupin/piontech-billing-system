<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Kartu angka halaman Komisi: komisi yang harus dibayar periode ini, estimasi
 * komisi dari pelanggan yang belum bayar, dan jumlah petugas aktif.
 * Petugas non-aktif tetap ikut di dua kartu pertama — sama seperti tabel
 * & commissionTotal().
 */
class CommissionSummary extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 3;

    protected function getStats(): array
    {
        $period = $this->periodStart();
        $service = app(BillingService::class);

        return [
            Stat::make(__('Total Commission'), BillingStatsOverview::rupiah($service->commissionTotal($period)))
                // Description wajib ada: warna kartu hanya ter-render lewatnya.
                ->description($period->translatedFormat('F Y'))
                ->icon('heroicon-o-receipt-percent')
                ->color('success'),
            Stat::make(__('Estimated Commission'), BillingStatsOverview::rupiah($service->commissionEstimateTotal($period)))
                ->description(__('Customers not yet paid'))
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make(
                __('Officers'),
                (string) User::where('role', Role::FieldOfficer)->where('is_active', true)->count(),
            )
                ->description(__('Active field officers'))
                ->icon('heroicon-o-users')
                ->color('info'),
        ];
    }

    private function periodStart(): Carbon
    {
        return Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();
    }
}
