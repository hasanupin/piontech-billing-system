<?php

namespace App\Filament\Widgets;

use App\Enums\RecipientType;
use App\Models\CommissionRecipient;
use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Kartu angka halaman Komisi: komisi yang harus dibayar periode ini, estimasi
 * komisi dari pelanggan referal yang belum bayar, dan jumlah penerima per jenis.
 * Penerima non-aktif tetap ikut — sama seperti tabel & commissionTotal().
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
                ->description(__('Referred customers not yet paid'))
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make(
                __('Recipients'),
                CommissionRecipient::where('type', RecipientType::External)->count()
                    .' / '.CommissionRecipient::where('type', RecipientType::Customer)->count(),
            )
                ->description(__('Non-Customer / Customer'))
                ->icon('heroicon-o-users')
                ->color('info'),
        ];
    }

    private function periodStart(): Carbon
    {
        return Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();
    }
}
