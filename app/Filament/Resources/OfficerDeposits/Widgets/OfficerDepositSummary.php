<?php

namespace App\Filament\Resources\OfficerDeposits\Widgets;

use App\Enums\Role;
use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OfficerDepositSummary extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $user = auth()->user();
        $billing = app(BillingService::class);
        $period = Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();

        // Petugas: hanya sisa (KEKURANGAN SETOR) miliknya bulan ini.
        if ($user?->isRole(Role::FieldOfficer)) {
            $progress = $billing->officerProgress((int) $user->id, $period);

            return [
                Stat::make(__('Remaining To Deposit This Period'), 'Rp '.number_format($progress['remaining'], 0, ',', '.')),
                Stat::make(__('Not Collected Yet'), 'Rp '.number_format($progress['uncollected'], 0, ',', '.')),
            ];
        }

        // Admin/super admin: ringkasan seluruh petugas bulan ini.
        $summary = $billing->monthlySummary($period);

        return [
            Stat::make(__('Cash Collected'), 'Rp '.number_format($summary['cash'], 0, ',', '.')),
            Stat::make(__('Not Collected Yet'), 'Rp '.number_format(
                $billing->billingProgress($period)['uncollected'], 0, ',', '.',
            )),
            Stat::make(__('Total Deposited'), 'Rp '.number_format($summary['total_deposited'], 0, ',', '.')),
            Stat::make(__('Held By Officers'), 'Rp '.number_format($summary['held_by_officers'], 0, ',', '.')),
        ];
    }
}
