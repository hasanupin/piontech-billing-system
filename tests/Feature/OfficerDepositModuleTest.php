<?php

namespace Tests\Feature;

use App\Filament\Resources\OfficerDeposits\OfficerDepositResource;
use App\Filament\Resources\OfficerDeposits\Pages\CreateOfficerDeposit;
use App\Filament\Resources\OfficerDeposits\Pages\ListOfficerDeposits;
use App\Filament\Widgets\OfficerDepositWidget;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OfficerDepositModuleTest extends TestCase
{
    use RefreshDatabase;

    public function testCalculatesRemainingBalanceAfterMultipleDeposits(): void
    {
        $officer = User::factory()->fieldOfficer()->create();

        // Tunai terkumpul: 3.000.000 (3 x 1jt).
        Transaction::factory()->count(3)->create([
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'paid_amount' => 1_000_000,
            'period' => now()->startOfMonth(),
        ]);
        // TITIP 1: 1jt, TITIP 2: 500rb.
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 1_000_000,
            'period' => now()->startOfMonth(),
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 500_000,
            'period' => now()->startOfMonth(),
        ]);

        // Sisa = 3jt - 1,5jt = 1,5jt.
        $this->assertSame(
            1_500_000.0,
            app(BillingService::class)->officerRemainingBalance($officer->id, now()),
        );
    }

    public function testFieldOfficerCanCreateDepositForThemselves(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($officer);

        Livewire::test(CreateOfficerDeposit::class)
            ->fillForm([
                'amount' => '500000',
                'received_by' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $deposit = OfficerDeposit::first();
        $this->assertSame($officer->id, $deposit->officer_id);
    }

    public function testFieldOfficerCanAccessDepositResource(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(OfficerDepositResource::getUrl('index'))->assertOk();
    }

    public function testFieldOfficerOnlySeesOwnDeposits(): void
    {
        $mine = User::factory()->fieldOfficer()->create();
        $other = User::factory()->fieldOfficer()->create();
        OfficerDeposit::factory()->count(2)->create(['officer_id' => $mine->id]);
        OfficerDeposit::factory()->count(3)->create(['officer_id' => $other->id]);

        $this->actingAs($mine);

        $query = OfficerDepositResource::getEloquentQuery();
        $this->assertSame(2, $query->count());
    }

    public function testAdminCanExportDepositsToExcel(): void
    {
        OfficerDeposit::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListOfficerDeposits::class)
            ->callAction('export')
            ->assertFileDownloaded();
    }

    public function testFieldOfficerCannotExportDeposits(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        Livewire::test(ListOfficerDeposits::class)
            ->assertActionHidden('export');
    }

    /**
     * Petugas dengan cluster berisi 2 pelanggan: 100rb bayar tunai,
     * 150rb bayar transfer, plus setoran 40rb.
     */
    private function officerWithProgress(): User
    {
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);

        $tunai = Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 100_000]);
        $transfer = Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 150_000]);

        Transaction::factory()->create([
            'customer_id' => $tunai->id,
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'period' => now()->startOfMonth(),
            'billed_amount' => 100_000,
            'paid_amount' => 100_000,
        ]);
        Transaction::factory()->transfer()->create([
            'customer_id' => $transfer->id,
            'period' => now()->startOfMonth(),
            'billed_amount' => 150_000,
            'paid_amount' => 150_000,
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'period' => now()->startOfMonth(),
            'amount' => 40_000,
        ]);

        return $officer;
    }

    public function testOfficerProgressCountsTargetCollectedDepositedAndRemaining(): void
    {
        $officer = $this->officerWithProgress();

        $progress = app(BillingService::class)->officerProgress($officer->id, now());

        // Harus ditarik = tagihan cluster (250rb) − yang dibayar transfer (150rb).
        $this->assertSame(100_000.0, $progress['target']);
        $this->assertSame(100_000.0, $progress['collected']);
        $this->assertSame(40_000.0, $progress['deposited']);
        $this->assertSame(60_000.0, $progress['remaining']);
    }

    public function testOfficerProgressIgnoresOtherOfficersAndPeriods(): void
    {
        $officer = $this->officerWithProgress();

        // Petugas lain — cluster, transaksi, dan setorannya sendiri.
        $other = User::factory()->fieldOfficer()->create();
        $otherCluster = Cluster::factory()->create(['officer_id' => $other->id]);
        $otherCustomer = Customer::factory()->create([
            'cluster_id' => $otherCluster->id,
            'billing_amount' => 999_000,
        ]);
        Transaction::factory()->create([
            'customer_id' => $otherCustomer->id,
            'officer_id' => $other->id,
            'payment_method' => 'cash',
            'period' => now()->startOfMonth(),
            'billed_amount' => 999_000,
            'paid_amount' => 999_000,
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $other->id,
            'period' => now()->startOfMonth(),
            'amount' => 777_000,
        ]);

        // Bulan lain milik petugas yang diuji.
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'period' => now()->startOfMonth()->subMonth(),
            'amount' => 555_000,
        ]);

        $progress = app(BillingService::class)->officerProgress($officer->id, now());

        $this->assertSame(100_000.0, $progress['target']);
        $this->assertSame(100_000.0, $progress['collected']);
        $this->assertSame(40_000.0, $progress['deposited']);
    }

    public function testOfficerProgressTargetExcludesTerminatedCustomers(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);
        Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 100_000]);
        Customer::factory()->terminated()->create(['cluster_id' => $cluster->id, 'billing_amount' => 999_000]);

        $this->assertSame(
            100_000.0,
            app(BillingService::class)->officerProgress($officer->id, now())['target'],
        );
    }

    public function testRemainingBalanceStillMatchesOfficerProgress(): void
    {
        $officer = $this->officerWithProgress();
        $billing = app(BillingService::class);

        $this->assertSame(
            $billing->officerProgress($officer->id, now())['remaining'],
            $billing->officerRemainingBalance($officer->id, now()),
        );
    }

    public function testProgressIsRecomputedAfterNewDepositInSameRequest(): void
    {
        $officer = $this->officerWithProgress();
        $billing = app(BillingService::class);

        $this->assertSame(40_000.0, $billing->officerProgress($officer->id, now())['deposited']);

        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'period' => now()->startOfMonth(),
            'amount' => 10_000,
        ]);

        // Memo tidak boleh menyajikan angka lama setelah ada setoran baru.
        $this->assertSame(50_000.0, $billing->officerProgress($officer->id, now())['deposited']);
    }

    public function testDepositListFiltersByPeriod(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $thisMonth = OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'period' => now()->startOfMonth(),
        ]);
        // startOfMonth dulu: subMonth pada tanggal 31 melompat balik ke bulan yang sama.
        $lastMonth = OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'period' => now()->startOfMonth()->subMonth(),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListOfficerDeposits::class)
            ->assertCanSeeTableRecords([$thisMonth])
            ->assertCanNotSeeTableRecords([$lastMonth])
            ->set('filters.period', now()->startOfMonth()->subMonth()->format('Y-m'))
            ->assertCanSeeTableRecords([$lastMonth])
            ->assertCanNotSeeTableRecords([$thisMonth]);
    }

    public function testDepositTableShowsMustCollectColumn(): void
    {
        $this->officerWithProgress();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListOfficerDeposits::class)
            ->assertCanRenderTableColumn('must_collect')
            // money() memakai non-breaking space, beda dengan helper rupiah() sendiri.
            ->assertSee("Rp\u{A0}100.000");
    }

    public function testPerOfficerPanelListsEveryOfficerIncludingZeroes(): void
    {
        $active = $this->officerWithProgress();
        $idle = User::factory()->fieldOfficer()->create(['name' => 'Petugas Nganggur']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(OfficerDepositWidget::class, [
            'pageFilters' => ['period' => now()->format('Y-m')],
        ])
            ->assertCanSeeTableRecords([$active, $idle])
            ->assertSee('Petugas Nganggur')
            ->assertSee('Rp 40.000')   // sudah disetor
            ->assertSee('Rp 60.000');  // sisa di petugas
    }

    public function testFieldOfficerOnlySeesOwnRowInPanel(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $other = User::factory()->fieldOfficer()->create(['name' => 'Petugas Lain']);
        $this->actingAs($officer);

        Livewire::test(OfficerDepositWidget::class, [
            'pageFilters' => ['period' => now()->format('Y-m')],
        ])
            ->assertCanSeeTableRecords([$officer])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function testFilterRendersAboveWidgetsAndTable(): void
    {
        $officer = User::factory()->fieldOfficer()->create(['name' => 'Budi Petugas']);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'period' => now()->startOfMonth(),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(OfficerDepositResource::getUrl('index'))->assertSeeInOrder([
            'filters.period',         // filter halaman
            'OfficerDepositWidget',   // panel per petugas
            'Budi Petugas',           // baris tabel
        ], escape: false);
    }
}
