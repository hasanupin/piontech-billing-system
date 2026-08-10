<?php

namespace Tests\Feature;

use App\Filament\Widgets\BillingStatsOverview;
use App\Filament\Widgets\DueTodayWidget;
use App\Filament\Widgets\OfficerDepositWidget;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function testDashboardPageRendersWithPeriodFilter(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin')->assertOk()->assertSee(__('Period'));
    }

    public function testStatsWidgetShowsCorrectUangDiPetugas(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $officer = User::factory()->fieldOfficer()->create();

        // Tunai 2jt, transfer 1jt, setor 500rb → di petugas HARUS 1,5jt (transfer tidak masuk).
        Transaction::factory()->count(2)->create([
            'officer_id' => $officer->id,
            'paid_amount' => 1_000_000,
        ]);
        Transaction::factory()->transfer()->create(['paid_amount' => 1_000_000]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 500_000,
            'period' => now()->startOfMonth(),
        ]);

        Livewire::test(BillingStatsOverview::class)
            ->assertSee('Rp 1.500.000');
    }

    public function testIsolirPelangganCountedInDitagih(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Customer::factory()->count(2)->create();
        Customer::factory()->suspended()->create();
        Customer::factory()->terminated()->create();

        // Ditagih = aktif + isolir = 3 (terminated tidak dihitung).
        Livewire::test(BillingStatsOverview::class)
            ->assertSee('Aktif: 2')
            ->assertSee('ISOLIR: 1');
    }

    public function testPetugasDashboardOnlyShowsOwnClusterStats(): void
    {
        $mine = User::factory()->fieldOfficer()->create();
        $other = User::factory()->fieldOfficer()->create();

        Transaction::factory()->create(['officer_id' => $mine->id, 'paid_amount' => 750_000]);
        Transaction::factory()->create(['officer_id' => $other->id, 'paid_amount' => 250_000]);

        $this->actingAs($mine);

        // Uang di petugas versi petugas login = hanya tunai miliknya (750rb).
        Livewire::test(BillingStatsOverview::class)
            ->assertSee('Rp 750.000')
            ->assertDontSee('Rp 1.000.000');
    }

    public function testPeriodFilterAffectsWidgets(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Transaction::factory()->create(['paid_amount' => 1_000_000]);
        Transaction::factory()->create([
            'paid_amount' => 400_000,
            'period' => now()->startOfMonth()->subMonth(),
        ]);

        Livewire::test(BillingStatsOverview::class, [
            'pageFilters' => ['period' => now()->startOfMonth()->subMonth()->format('Y-m')],
        ])
            ->assertSee('Rp 400.000')
            ->assertDontSee('Rp 1.000.000');
    }

    public function testOfficerDepositWidgetShowsRemainingBalance(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $officer = User::factory()->fieldOfficer()->create(['name' => 'Petugas Uji']);

        Transaction::factory()->create(['officer_id' => $officer->id, 'paid_amount' => 1_000_000]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 400_000,
            'period' => now()->startOfMonth(),
        ]);

        // Sisa = 1jt − 400rb = 600rb.
        Livewire::test(OfficerDepositWidget::class)
            ->assertSee('Petugas Uji')
            ->assertSee('600.000');
    }

    public function testStatsCarrySparklineOverSixMonths(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $stats = Livewire::test(BillingStatsOverview::class)->instance()->sparkline('cash');

        $this->assertCount(6, $stats);
    }

    public function testStatsRenderOnEmptyDatabase(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Sparkline 6 bulan menambah loop + pembagian; DB kosong harus tetap aman.
        Livewire::test(BillingStatsOverview::class)->assertOk();
    }

    public function testDueTodayWidgetShowsUnpaidCustomersDueToday(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $cluster = Cluster::factory()->create(['name' => 'Cluster Uji']);

        Customer::factory()->create([
            'name' => 'Jatuh Tempo',
            'cluster_id' => $cluster->id,
            'billing_day' => now()->day,
        ]);
        Customer::factory()->create([
            'name' => 'Belum Tempo',
            'billing_day' => now()->day === 1 ? 28 : 1,
        ]);

        Livewire::test(DueTodayWidget::class)
            ->assertSee('Jatuh Tempo')
            ->assertSee('Cluster Uji')
            ->assertDontSee('Belum Tempo');
    }
}
