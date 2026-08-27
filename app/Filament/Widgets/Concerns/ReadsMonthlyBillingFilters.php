<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\Role;
use App\Models\Cluster;
use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Ringkasan halaman Tagihan Bulanan membaca periode & cluster dari filter
 * halaman (HasFiltersForm). $pageFilters #[Reactive], jadi widget ikut
 * ter-update saat filter diganti tanpa event manual.
 *
 * Filter "Status Bayar" sengaja diabaikan: kalau ikut, memfilter "Belum Bayar"
 * membuat grafik lunas-vs-belum jadi 100% dan kehilangan maknanya.
 */
trait ReadsMonthlyBillingFilters
{
    use InteractsWithPageFilters;

    protected function periodStart(): Carbon
    {
        return Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();
    }

    protected function clusterId(): ?string
    {
        return $this->pageFilters['cluster_id'] ?? null;
    }

    /**
     * @return array{billed: int, paid: int, unpaid: int, billed_amount: float, paid_amount: float, outstanding: float, cash: float, transfer: float, must_deposit: float, uncollected: float, deposited: float, not_deposited: float}
     */
    protected function progress(): array
    {
        $clusterId = $this->clusterId();
        $user = auth()->user();

        // Setoran mengikuti PIC cluster terpilih; tanpa filter cluster,
        // petugas login hanya melihat setorannya sendiri.
        $officerId = $clusterId
            ? Cluster::find($clusterId)?->officer_id
            : ($user?->isRole(Role::FieldOfficer) ? $user->id : null);

        return app(BillingService::class)->billingProgress(
            $this->periodStart(),
            $clusterId,
            $officerId,
        );
    }
}
