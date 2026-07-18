<?php

namespace App\Filament\Widgets;

use App\Enums\CustomerStatus;
use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * 5 stat utama billing (TASK-10). Kritis: "Uang di Petugas" = tunai − setor;
 * transfer TIDAK pernah masuk (langsung settled ke rekening).
 */
class BillingStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = auth()->user();
        $period = $this->periodStart();

        // Petugas login → angka uang dibatasi ke dirinya sendiri.
        $officerId = $user?->isRole(Role::FieldOfficer) ? $user->id : null;
        $summary = app(BillingService::class)->monthlySummary($period, $officerId);

        // Customer sudah auto-terscope cluster petugas via global scope.
        $aktif = Customer::billable()->where('status', CustomerStatus::Active)->count();
        $isolir = Customer::billable()->where('status', CustomerStatus::Suspended)->count();
        $ditagih = $aktif + $isolir;

        $lunas = Customer::billable()->whereHas(
            'transactions',
            fn ($q) => $q->forPeriod($period)->where('status', TransactionStatus::Paid),
        )->count();

        return [
            Stat::make(__('Billed Customers'), $ditagih)
                ->description(__('Aktif: :active | ISOLIR: :suspended', ['active' => $aktif, 'suspended' => $isolir])),
            Stat::make(__('Paid'), $lunas)
                ->description(round($lunas / max(1, $ditagih) * 100, 1).'%')
                ->color('success'),
            Stat::make(__('Unpaid'), $ditagih - $lunas)
                ->color('danger'),
            Stat::make(__('Via Transfer'), self::rupiah($summary['transfer']))
                ->description(__('Settled directly'))
                ->color('info'),
            Stat::make(__('Held By Officers'), self::rupiah($summary['held_by_officers']))
                ->description(__('Cash collected − deposited'))
                ->color('warning'),
        ];
    }

    public static function rupiah(float $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }

    private function periodStart(): Carbon
    {
        return Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();
    }
}
