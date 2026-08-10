<?php

namespace App\Filament\Widgets;

use App\Enums\CustomerStatus;
use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Strip status jaringan di puncak dashboard — ringkasan sekilas ala konsol NOC.
 * Angka uang memakai formula yang sama dengan BillingStatsOverview: transfer
 * langsung settled, jadi tidak pernah masuk "uang di petugas".
 */
class NetworkStatusStrip extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.network-status-strip';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    /**
     * @return array{active: int, suspended: int, billed: int, paid: int, due_today: int, collection_rate: float, held_by_officers: float}
     */
    public function metrics(): array
    {
        $user = auth()->user();
        $period = $this->periodStart();

        // Petugas login → angka uang dibatasi ke dirinya sendiri (pola sama
        // dengan BillingStatsOverview). Hitungan pelanggan sudah ter-scope
        // otomatis lewat global scope cluster di model Customer.
        $officerId = $user?->isRole(Role::FieldOfficer) ? $user->id : null;

        $active = Customer::billable()->where('status', CustomerStatus::Active)->count();
        $suspended = Customer::billable()->where('status', CustomerStatus::Suspended)->count();
        $billed = $active + $suspended;

        $paid = Customer::billable()->whereHas(
            'transactions',
            fn (Builder $q) => $q->forPeriod($period)->where('status', TransactionStatus::Paid),
        )->count();

        $dueToday = Customer::query()
            ->dueToday()
            ->whereDoesntHave('transactions', fn (Builder $q) => $q
                ->forPeriod(now()->startOfMonth())
                ->where('status', TransactionStatus::Paid))
            ->count();

        return [
            'active' => $active,
            'suspended' => $suspended,
            'billed' => $billed,
            'paid' => $paid,
            'due_today' => $dueToday,
            // max(1, ...) menjaga dari pembagian nol saat belum ada pelanggan.
            'collection_rate' => round($paid / max(1, $billed) * 100, 1),
            'held_by_officers' => app(BillingService::class)->monthlySummary($period, $officerId)['held_by_officers'],
        ];
    }

    public function periodLabel(): string
    {
        return $this->periodStart()->translatedFormat('F Y');
    }

    private function periodStart(): Carbon
    {
        return Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();
    }
}
