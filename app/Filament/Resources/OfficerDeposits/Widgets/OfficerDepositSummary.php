<?php

namespace App\Filament\Resources\OfficerDeposits\Widgets;

use App\Enums\Role;
use App\Services\BillingService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OfficerDepositSummary extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $billing = app(BillingService::class);

        // Petugas: hanya sisa (KEKURANGAN SETOR) miliknya bulan ini.
        if ($user?->isRole(Role::FieldOfficer)) {
            $remaining = $billing->officerRemainingBalance((int) $user->id, now());

            return [
                Stat::make(__('Remaining To Deposit This Period'), 'Rp '.number_format($remaining, 0, ',', '.')),
            ];
        }

        // Admin/super admin: ringkasan seluruh petugas bulan ini.
        $summary = $billing->monthlySummary(now());

        return [
            Stat::make(__('Cash Collected'), 'Rp '.number_format($summary['cash'], 0, ',', '.')),
            Stat::make(__('Total Deposited'), 'Rp '.number_format($summary['total_deposited'], 0, ',', '.')),
            Stat::make(__('Held By Officers'), 'Rp '.number_format($summary['held_by_officers'], 0, ',', '.')),
        ];
    }
}
