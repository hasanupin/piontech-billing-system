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

    protected int|string|array $columnSpan = 'full';

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
                ->description(__('Aktif: :active | ISOLIR: :suspended', ['active' => $aktif, 'suspended' => $isolir]))
                ->icon('heroicon-o-signal'),
            Stat::make(__('Paid'), $lunas)
                ->description(round($lunas / max(1, $ditagih) * 100, 1).'%')
                ->icon('heroicon-o-check-badge')
                ->color('success'),
            Stat::make(__('Unpaid'), $ditagih - $lunas)
                // Description bukan sekadar hiasan: warna stat hanya ter-render
                // lewat description/chart, dan garis aksen kartu membacanya.
                ->description(round(($ditagih - $lunas) / max(1, $ditagih) * 100, 1).'%')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
            Stat::make(__('Via Transfer'), self::rupiah($summary['transfer']))
                ->description(__('Settled directly'))
                ->icon('heroicon-o-arrows-right-left')
                ->chart($this->sparkline('transfer'))
                ->color('info'),
            Stat::make(__('Held By Officers'), self::rupiah($summary['held_by_officers']))
                ->description(__('Cash collected − deposited'))
                ->icon('heroicon-o-banknotes')
                ->chart($this->sparkline('held_by_officers'))
                ->color('warning'),
            Stat::make(__('Total Deposited'), self::rupiah($summary['total_deposited']))
                ->description(__('Received by admin'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->chart($this->sparkline('total_deposited'))
                ->color('success'),
        ];
    }

    /**
     * Tren 6 bulan terakhir untuk satu key BillingService::monthlySummary().
     * Hanya dipasang di stat uang — stat hitungan pelanggan tidak butuh tren.
     *
     * @return array<int, float>
     */
    public function sparkline(string $key): array
    {
        $user = auth()->user();
        $officerId = $user?->isRole(Role::FieldOfficer) ? $user->id : null;
        $billing = app(BillingService::class);
        $cursor = $this->periodStart()->subMonths(5);
        $points = [];

        for ($i = 0; $i < 6; $i++) {
            $points[] = $billing->monthlySummary($cursor->copy(), $officerId)[$key] ?? 0.0;
            $cursor->addMonth();
        }

        return $points;
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
