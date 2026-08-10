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

    /**
     * @return array{billed: int, paid: int, unpaid: int, paid_amount: float, outstanding: float, deposited: float}
     */
    protected function progress(): array
    {
        $clusterId = $this->pageFilters['cluster_id'] ?? null;
        $user = auth()->user();

        // Setoran mengikuti PIC cluster terpilih; tanpa filter cluster,
        // petugas login hanya melihat setorannya sendiri.
        $officerId = $clusterId
            ? Cluster::find($clusterId)?->officer_id
            : ($user?->isRole(Role::FieldOfficer) ? $user->id : null);

        return app(BillingService::class)->billingProgress(
            Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01'),
            $clusterId,
            $officerId,
        );
    }
}
