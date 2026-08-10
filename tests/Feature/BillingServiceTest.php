<?php

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\Customer;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BillingService
    {
        return app(BillingService::class);
    }

    public function testMonthlySummaryExcludesTransferFromHeldByOfficers(): void
    {
        $period = now()->startOfMonth();
        $officer = User::factory()->fieldOfficer()->create();

        // tunai 500rb (via officer) + transfer 300rb + setor 200rb
        Transaction::factory()->create([
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'billed_amount' => 500000,
            'paid_amount' => 500000,
            'period' => $period,
        ]);
        Transaction::factory()->transfer()->create([
            'billed_amount' => 300000,
            'paid_amount' => 300000,
            'period' => $period,
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 200000,
            'period' => $period,
        ]);

        $summary = $this->service()->monthlySummary($period);

        $this->assertEquals(500000, $summary['cash']);
        $this->assertEquals(300000, $summary['transfer']);
        $this->assertEquals(800000, $summary['total_collected']);
        $this->assertEquals(200000, $summary['total_deposited']);
        // di petugas = tunai - setor = 300rb, BUKAN 600rb (transfer tidak ikut)
        $this->assertEquals(300000, $summary['held_by_officers']);
    }

    public function testOfficerRemainingBalanceIsCashMinusDeposits(): void
    {
        $period = now()->startOfMonth();
        $officer = User::factory()->fieldOfficer()->create();
        $other = User::factory()->fieldOfficer()->create();

        Transaction::factory()->create([
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'billed_amount' => 400000,
            'paid_amount' => 400000,
            'period' => $period,
        ]);
        Transaction::factory()->create([
            'officer_id' => $other->id,
            'payment_method' => 'cash',
            'billed_amount' => 999000,
            'paid_amount' => 999000,
            'period' => $period,
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 150000,
            'period' => $period,
        ]);

        $this->assertEquals(
            250000,
            $this->service()->officerRemainingBalance($officer->id, $period),
        );
    }

    public function testRangeSummaryFiltersByPaidAtAndDepositedAt(): void
    {
        $officer = User::factory()->fieldOfficer()->create();

        // dalam range: tunai 500rb + transfer 300rb + setor 200rb
        Transaction::factory()->create([
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'billed_amount' => 500000,
            'paid_amount' => 500000,
            'paid_at' => now(),
        ]);
        Transaction::factory()->transfer()->create([
            'billed_amount' => 300000,
            'paid_amount' => 300000,
            'paid_at' => now(),
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 200000,
            'deposited_at' => now(),
        ]);

        // luar range (2 bulan lalu) — harus diabaikan
        Transaction::factory()->create([
            'payment_method' => 'cash',
            'billed_amount' => 999000,
            'paid_amount' => 999000,
            'paid_at' => now()->subMonths(2),
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 777000,
            'deposited_at' => now()->subMonths(2),
        ]);

        $summary = $this->service()->rangeSummary(now()->startOfMonth(), now()->endOfMonth());

        $this->assertEquals(500000, $summary['cash']);
        $this->assertEquals(300000, $summary['transfer']);
        $this->assertEquals(800000, $summary['total_collected']);
        $this->assertEquals(200000, $summary['total_deposited']);
        $this->assertEquals(300000, $summary['held_by_officers']);
    }

    public function testRangeSummaryScopesToOfficer(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $other = User::factory()->fieldOfficer()->create();

        Transaction::factory()->create([
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'billed_amount' => 400000,
            'paid_amount' => 400000,
            'paid_at' => now(),
        ]);
        Transaction::factory()->create([
            'officer_id' => $other->id,
            'payment_method' => 'cash',
            'billed_amount' => 999000,
            'paid_amount' => 999000,
            'paid_at' => now(),
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 150000,
            'deposited_at' => now(),
        ]);

        $summary = $this->service()->rangeSummary(now()->startOfMonth(), now()->endOfMonth(), $officer->id);

        $this->assertEquals(400000, $summary['cash']);
        // sisa = tunai officer − setoran officer
        $this->assertEquals(250000, $summary['held_by_officers']);
    }

    public function testBillingProgressCountsPaidAndUnpaidWithAmounts(): void
    {
        $period = now()->startOfMonth();
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);

        $lunas = Customer::factory()->count(2)->create([
            'cluster_id' => $cluster->id,
            'billing_amount' => 100000,
        ]);
        Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 150000]);
        // Terminated tidak ditagih → tidak boleh ikut hitungan mana pun.
        Customer::factory()->terminated()->create(['cluster_id' => $cluster->id, 'billing_amount' => 999000]);

        foreach ($lunas as $customer) {
            Transaction::factory()->create([
                'customer_id' => $customer->id,
                'officer_id' => $officer->id,
                'period' => $period,
                'billed_amount' => 100000,
                'paid_amount' => 100000,
            ]);
        }
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'period' => $period,
            'amount' => 120000,
        ]);

        $progress = $this->service()->billingProgress($period);

        $this->assertSame(3, $progress['billed']);
        $this->assertSame(2, $progress['paid']);
        $this->assertSame(1, $progress['unpaid']);
        $this->assertEquals(200000, $progress['paid_amount']);
        $this->assertEquals(150000, $progress['outstanding']);
        $this->assertEquals(120000, $progress['deposited']);
    }

    public function testBillingProgressScopesToCluster(): void
    {
        $period = now()->startOfMonth();
        $mine = Cluster::factory()->create();
        $other = Cluster::factory()->create();

        $lunas = Customer::factory()->create(['cluster_id' => $mine->id, 'billing_amount' => 100000]);
        Customer::factory()->create(['cluster_id' => $mine->id, 'billing_amount' => 150000]);
        Customer::factory()->create(['cluster_id' => $other->id, 'billing_amount' => 999000]);

        Transaction::factory()->create([
            'customer_id' => $lunas->id,
            'period' => $period,
            'billed_amount' => 100000,
            'paid_amount' => 100000,
        ]);

        $progress = $this->service()->billingProgress($period, $mine->id);

        $this->assertSame(2, $progress['billed']);
        $this->assertSame(1, $progress['paid']);
        $this->assertEquals(100000, $progress['paid_amount']);
        $this->assertEquals(150000, $progress['outstanding']);
    }

    public function testBillingProgressIgnoresOtherPeriodsAndOtherOfficersDeposits(): void
    {
        $period = now()->startOfMonth();
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);
        $customer = Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 100000]);

        // Lunas bulan lalu → periode ini tetap belum bayar.
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'period' => $period->copy()->subMonth(),
            'billed_amount' => 100000,
            'paid_amount' => 100000,
        ]);
        OfficerDeposit::factory()->create(['period' => $period, 'amount' => 777000]);

        $progress = $this->service()->billingProgress($period, null, $officer->id);

        $this->assertSame(0, $progress['paid']);
        $this->assertSame(1, $progress['unpaid']);
        $this->assertEquals(0, $progress['paid_amount']);
        $this->assertEquals(100000, $progress['outstanding']);
        // Setoran petugas lain tidak ikut.
        $this->assertEquals(0, $progress['deposited']);
    }

    /**
     * Fixture bersama untuk angka grafik setoran: A tunai lunas, B transfer lunas,
     * C belum bayar, plus satu setoran petugas.
     *
     * @return array{0: Cluster, 1: User}
     */
    private function depositChartFixture(): array
    {
        $period = now()->startOfMonth();
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);

        $tunai = Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 100000]);
        $transfer = Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 150000]);
        Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 200000]);

        Transaction::factory()->create([
            'customer_id' => $tunai->id,
            'officer_id' => $officer->id,
            'period' => $period,
            'billed_amount' => 100000,
            'paid_amount' => 100000,
        ]);
        Transaction::factory()->transfer()->create([
            'customer_id' => $transfer->id,
            'period' => $period,
            'billed_amount' => 150000,
            'paid_amount' => 150000,
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'period' => $period,
            'amount' => 40000,
        ]);

        return [$cluster, $officer];
    }

    public function testBillingProgressReturnsDepositChartAmounts(): void
    {
        $this->depositChartFixture();

        $progress = $this->service()->billingProgress(now()->startOfMonth());

        $this->assertEquals(450000, $progress['billed_amount']);
        $this->assertEquals(250000, $progress['paid_amount']);
        $this->assertEquals(200000, $progress['outstanding']);
        $this->assertEquals(100000, $progress['cash']);
        $this->assertEquals(150000, $progress['transfer']);
        // Harus disetor = seluruh tagihan yang tidak dibayar transfer (450 − 150).
        $this->assertEquals(300000, $progress['must_deposit']);
        $this->assertEquals(40000, $progress['deposited']);
        // Uang yang belum sampai ke admin = harus − sudah.
        $this->assertEquals(260000, $progress['not_deposited']);
    }

    public function testBillingProgressKeepsTransferVisibleWhenScopedToCluster(): void
    {
        [$cluster, $officer] = $this->depositChartFixture();

        $progress = $this->service()->billingProgress(now()->startOfMonth(), $cluster->id, $officer->id);

        // Transaksi transfer punya officer_id null — angkanya tidak boleh hilang
        // saat halaman difilter per cluster / dibuka petugas.
        $this->assertEquals(150000, $progress['transfer']);
        $this->assertEquals(100000, $progress['cash']);
        $this->assertEquals(450000, $progress['billed_amount']);
        $this->assertEquals(300000, $progress['must_deposit']);
        $this->assertEquals(260000, $progress['not_deposited']);
    }

    public function testHeldByOfficersKeepsCashMinusDepositDefinition(): void
    {
        $this->depositChartFixture();

        // Definisi lama (dipakai Dashboard, strip status, laporan) tidak ikut
        // bergeser: tunai 100rb − setor 40rb = 60rb, bukan 260rb.
        $this->assertEquals(
            60000,
            $this->service()->monthlySummary(now()->startOfMonth())['held_by_officers'],
        );
    }

    public function testBillingProgressExcludesOtherClusterAmounts(): void
    {
        [$cluster] = $this->depositChartFixture();
        $lain = Cluster::factory()->create();
        $customer = Customer::factory()->create(['cluster_id' => $lain->id, 'billing_amount' => 999000]);
        Transaction::factory()->transfer()->create([
            'customer_id' => $customer->id,
            'period' => now()->startOfMonth(),
            'billed_amount' => 999000,
            'paid_amount' => 999000,
        ]);

        $progress = $this->service()->billingProgress(now()->startOfMonth(), $cluster->id);

        $this->assertEquals(450000, $progress['billed_amount']);
        $this->assertEquals(150000, $progress['transfer']);
    }

    public function testMonthlySummaryIgnoresOtherPeriods(): void
    {
        $period = now()->startOfMonth();

        Transaction::factory()->create([
            'payment_method' => 'cash',
            'billed_amount' => 100000,
            'paid_amount' => 100000,
            'period' => $period->copy()->subMonth(),
        ]);

        $summary = $this->service()->monthlySummary($period);

        $this->assertEquals(0, $summary['cash']);
    }
}
