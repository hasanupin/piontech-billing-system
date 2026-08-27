<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Models\Customer;
use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Sisi bisnis dashboard (CEO & admin): komisi yang harus dibayar bulan itu dan
 * pergerakan jumlah pelanggan. Melengkapi BillingStatsOverview yang isinya uang
 * penagihan. Petugas tidak melihat widget ini.
 */
class BusinessStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->isRole(Role::SuperAdmin, Role::Admin) ?? false;
    }

    protected function getStats(): array
    {
        $period = $this->periodStart();
        $range = [$period->copy()->startOfMonth(), $period->copy()->endOfMonth()];

        $baru = Customer::whereBetween('registered_at', $range)->count();
        $isolir = Customer::whereBetween('suspended_at', $range)->count();
        $berhenti = Customer::whereBetween('terminated_at', $range)->count();

        return [
            Stat::make(
                __('Total Commission'),
                BillingStatsOverview::rupiah(app(BillingService::class)->commissionTotal($period)),
            )
                // Description wajib ada: warna kartu hanya ter-render lewatnya.
                ->description(__('Payable to field officers'))
                ->icon('heroicon-o-receipt-percent')
                ->color('success'),
            Stat::make(__('New Customers'), $baru)
                ->description($period->translatedFormat('F Y'))
                ->icon('heroicon-o-user-plus')
                ->color('info'),
            Stat::make(__('Churn'), $isolir.' / '.$berhenti)
                ->description(__('Newly Suspended').' / '.__('Terminated'))
                ->icon('heroicon-o-user-minus')
                ->color('danger'),
        ];
    }

    private function periodStart(): Carbon
    {
        return Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();
    }
}
