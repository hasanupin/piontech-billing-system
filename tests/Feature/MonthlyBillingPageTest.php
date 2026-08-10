<?php

namespace Tests\Feature;

use App\Filament\Pages\MonthlyBilling;
use App\Filament\Widgets\MonthlyBillingChart;
use App\Filament\Widgets\MonthlyBillingDepositChart;
use App\Filament\Widgets\MonthlyBillingSummary;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlyBillingPageTest extends TestCase
{
    use RefreshDatabase;

    public function testPageRendersWithSummaryWidgets(): void
    {
        Customer::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->get(MonthlyBilling::getUrl())->assertOk();
    }

    public function testFilterRendersAboveChartAndTable(): void
    {
        Customer::factory()->create(['name' => 'Budi Tester']);
        $this->actingAs(User::factory()->admin()->create());

        // Widget di-render sebagai komponen Livewire anak, jadi yang dicek
        // urutannya adalah placeholder-nya.
        $this->get(MonthlyBilling::getUrl())->assertSeeInOrder([
            'filters.period',               // filter halaman
            'MonthlyBillingChart',          // doughnut + kartu ringkasan
            'MonthlyBillingDepositChart',   // chart rekap uang
            'Budi Tester',                  // baris tabel
        ], escape: false);
    }

    public function testShowsFieldContextColumns(): void
    {
        Customer::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MonthlyBilling::class)
            ->assertCanRenderTableColumn('address')
            ->assertCanRenderTableColumn('whatsapp_number')
            ->assertCanRenderTableColumn('status');
    }

    public function testHousePhotoActionIsDisabledWithoutPhoto(): void
    {
        $withPhoto = Customer::factory()->create(['house_photo_url' => 'foto-rumah/rumah.jpg']);
        $withoutPhoto = Customer::factory()->create(['house_photo_url' => null]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MonthlyBilling::class)
            ->assertTableActionEnabled('house_photo', $withPhoto)
            ->assertTableActionDisabled('house_photo', $withoutPhoto);
    }

    public function testCheckLocationActionIsDisabledWithoutMapsUrl(): void
    {
        $withMaps = Customer::factory()->create(['maps_url' => 'https://maps.app.goo.gl/abc']);
        $withoutMaps = Customer::factory()->create(['maps_url' => null]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MonthlyBilling::class)
            ->assertTableActionEnabled('check_location', $withMaps)
            ->assertTableActionDisabled('check_location', $withoutMaps);
    }

    public function testPaymentStatusFilterSplitsPaidAndUnpaid(): void
    {
        $paid = Customer::factory()->create();
        $unpaid = Customer::factory()->create();
        Transaction::factory()->create([
            'customer_id' => $paid->id,
            'period' => now()->startOfMonth(),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MonthlyBilling::class)
            ->set('filters.payment_status', 'paid')
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$unpaid])
            ->set('filters.payment_status', 'unpaid')
            ->assertCanSeeTableRecords([$unpaid])
            ->assertCanNotSeeTableRecords([$paid]);
    }

    public function testClusterFilterLimitsRowsToThatCluster(): void
    {
        $mine = Cluster::factory()->create();
        $other = Cluster::factory()->create();
        $here = Customer::factory()->create(['cluster_id' => $mine->id]);
        $there = Customer::factory()->create(['cluster_id' => $other->id]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MonthlyBilling::class)
            ->set('filters.cluster_id', $mine->id)
            ->assertCanSeeTableRecords([$here])
            ->assertCanNotSeeTableRecords([$there]);
    }

    public function testPeriodFilterDrivesPaymentStatus(): void
    {
        $customer = Customer::factory()->create();
        // Lunas bulan lalu saja.
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'period' => now()->startOfMonth()->subMonth(),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MonthlyBilling::class)
            // Periode berjalan: belum bayar.
            ->set('filters.payment_status', 'unpaid')
            ->assertCanSeeTableRecords([$customer])
            // Pindah ke bulan lalu: lunas.
            // startOfMonth dulu: subMonth pada tanggal 31 melompat balik ke bulan yang sama.
            ->set('filters.period', now()->startOfMonth()->subMonth()->format('Y-m'))
            ->set('filters.payment_status', 'paid')
            ->assertCanSeeTableRecords([$customer]);
    }

    public function testSummaryWidgetFollowsClusterFilter(): void
    {
        $mine = Cluster::factory()->create();
        $other = Cluster::factory()->create();
        $here = Customer::factory()->create(['cluster_id' => $mine->id, 'billing_amount' => 100000]);
        Customer::factory()->create(['cluster_id' => $mine->id, 'billing_amount' => 150000]);
        Customer::factory()->create(['cluster_id' => $other->id, 'billing_amount' => 999000]);
        Transaction::factory()->create([
            'customer_id' => $here->id,
            'period' => now()->startOfMonth(),
            'billed_amount' => 100000,
            'paid_amount' => 100000,
        ]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MonthlyBillingSummary::class, [
            'pageFilters' => ['cluster_id' => $mine->id],
        ])
            ->assertSee('Rp 100.000')     // nominal lunas cluster ini
            ->assertSee('Rp 150.000')     // outstanding cluster ini
            ->assertDontSee('Rp 999.000'); // cluster lain tidak ikut
    }

    public function testChartWidgetRendersPaidVersusUnpaid(): void
    {
        $cluster = Cluster::factory()->create();
        $paid = Customer::factory()->create(['cluster_id' => $cluster->id]);
        Customer::factory()->count(2)->create(['cluster_id' => $cluster->id]);
        Transaction::factory()->create([
            'customer_id' => $paid->id,
            'period' => now()->startOfMonth(),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MonthlyBillingChart::class, [
            'pageFilters' => ['cluster_id' => $cluster->id],
        ])->assertSuccessful();
    }

    public function testDepositChartShowsEightAmountsForFilteredCluster(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);
        $tunai = Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 100000]);
        $transfer = Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 150000]);
        Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 200000]);
        // Cluster lain — tidak boleh ikut terhitung.
        Customer::factory()->create(['billing_amount' => 999000]);

        Transaction::factory()->create([
            'customer_id' => $tunai->id,
            'officer_id' => $officer->id,
            'period' => now()->startOfMonth(),
            'billed_amount' => 100000,
            'paid_amount' => 100000,
        ]);
        Transaction::factory()->transfer()->create([
            'customer_id' => $transfer->id,
            'period' => now()->startOfMonth(),
            'billed_amount' => 150000,
            'paid_amount' => 150000,
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'period' => now()->startOfMonth(),
            'amount' => 40000,
        ]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MonthlyBillingDepositChart::class, [
            'pageFilters' => ['cluster_id' => $cluster->id],
        ])
            ->assertSuccessful()
            // Data chart di-render sebagai JSON di atribut Alpine.
            ->assertSee('450000')  // nominal tagihan
            ->assertSee('250000')  // terbayar
            ->assertSee('200000')  // belum dibayar
            ->assertSee('100000')  // tunai
            ->assertSee('150000')  // transfer
            ->assertSee('300000')  // harus disetor
            ->assertSee('40000')   // sudah disetor
            ->assertSee('260000')  // belum disetor
            ->assertDontSee('999000');
    }

    public function testExportStreamsXlsxOfFilteredRows(): void
    {
        Customer::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MonthlyBilling::class)
            ->callAction('export')
            ->assertFileDownloaded();
    }

    public function testFieldOfficerCannotExport(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        Livewire::test(MonthlyBilling::class)
            ->assertActionHidden('export');
    }

    public function testFieldOfficerSeesOnlyOwnClusterRowsAndNumbers(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $mine = Cluster::factory()->create(['officer_id' => $officer->id]);
        $here = Customer::factory()->create(['cluster_id' => $mine->id]);
        $there = Customer::factory()->create();
        $this->actingAs($officer);

        Livewire::test(MonthlyBilling::class)
            ->assertCanSeeTableRecords([$here])
            ->assertCanNotSeeTableRecords([$there]);
    }
}
