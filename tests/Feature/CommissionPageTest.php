<?php

namespace Tests\Feature;

use App\Filament\Pages\Commission;
use App\Filament\Widgets\CommissionSummary;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Komisi petugas: jumlah pelanggan LUNAS di cluster petugas x tarif per
 * pelanggan yang tersimpan di user. Basisnya cluster, BUKAN officer_id
 * transaksi — pembayaran transfer selalu ber-officer_id NULL.
 */
class CommissionPageTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BillingService
    {
        return app(BillingService::class);
    }

    /** Komisi satu petugas pada periode berjalan. */
    private function commissionOf(User $officer): float
    {
        return $this->service()
            ->commissionQuery(now()->startOfMonth())
            ->find($officer->getKey())
            ->commission_amount;
    }

    /** Petugas + cluster, tarif komisi bisa ditentukan per test. */
    private function officer(float $fee = 4000): User
    {
        $officer = User::factory()->fieldOfficer()->create(['commission_per_customer' => $fee]);
        Cluster::factory()->create(['officer_id' => $officer->id]);

        return $officer;
    }

    /** Pelanggan di cluster petugas, opsional langsung dibayar lunas. */
    private function customer(User $officer, ?string $method = null, float $amount = 100_000): Customer
    {
        $customer = Customer::factory()->create([
            'cluster_id' => $officer->clusters()->value('id'),
            'billing_amount' => $amount,
        ]);

        if ($method !== null) {
            Transaction::factory()->create([
                'customer_id' => $customer->id,
                'officer_id' => $method === 'cash' ? $officer->id : null,
                'payment_method' => $method,
                'period' => now()->startOfMonth(),
                'billed_amount' => $amount,
                'paid_amount' => $amount,
            ]);
        }

        return $customer;
    }

    public function testAdminCanAccessCommissionPage(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(Commission::getUrl())->assertOk();
    }

    public function testFieldOfficerCannotAccessCommissionPage(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(Commission::getUrl())->assertForbidden();
    }

    public function testShowsEveryOfficerWithZeroWhenNobodyPaid(): void
    {
        $officers = collect([$this->officer(), $this->officer()]);
        $officers->each(fn (User $o) => $this->customer($o));

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Commission::class)->assertCanSeeTableRecords($officers);

        $officers->each(fn (User $o) => $this->assertSame(0.0, $this->commissionOf($o)));
    }

    public function testCountsEachPaidCustomerOnceAtTheOfficerRate(): void
    {
        $officer = $this->officer(4000);
        $this->customer($officer, 'cash');
        $this->customer($officer, 'cash');
        $this->customer($officer); // belum bayar

        $this->assertSame(8_000.0, $this->commissionOf($officer));
    }

    public function testTransferPaymentsStillEarnCommission(): void
    {
        // Regresi utama: Transaction::booted() menge-null-kan officer_id untuk
        // transfer, jadi hitungan berbasis officer_id akan menelan baris ini.
        $officer = $this->officer(4000);
        $this->customer($officer, 'transfer');

        $this->assertSame(4_000.0, $this->commissionOf($officer));
    }

    public function testIgnoresPartialUnpaidAndOtherPeriods(): void
    {
        $officer = $this->officer(4000);

        $partial = $this->customer($officer);
        Transaction::factory()->create([
            'customer_id' => $partial->id,
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'period' => now()->startOfMonth(),
            'billed_amount' => 100_000,
            'paid_amount' => 40_000,
        ]);

        $lastMonth = $this->customer($officer);
        Transaction::factory()->create([
            'customer_id' => $lastMonth->id,
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'period' => now()->subMonth()->startOfMonth(),
            'billed_amount' => 100_000,
            'paid_amount' => 100_000,
        ]);

        $this->assertSame(0.0, $this->commissionOf($officer));
    }

    public function testDoesNotCountOtherOfficersCustomers(): void
    {
        $mine = $this->officer(4000);
        $theirs = $this->officer(4000);
        $this->customer($theirs, 'cash');

        $this->assertSame(0.0, $this->commissionOf($mine));
        $this->assertSame(4_000.0, $this->commissionOf($theirs));
    }

    public function testUsesTheRatePerOfficer(): void
    {
        $murah = $this->officer(4000);
        $mahal = $this->officer(7500);
        $this->customer($murah, 'cash');
        $this->customer($mahal, 'cash');

        $this->assertSame(4_000.0, $this->commissionOf($murah));
        $this->assertSame(7_500.0, $this->commissionOf($mahal));
        $this->assertSame(11_500.0, $this->service()->commissionTotal(now()->startOfMonth()));
    }

    public function testEstimateCountsOnlyUnpaidBillableCustomers(): void
    {
        $officer = $this->officer(4000);
        $this->customer($officer, 'cash');   // sudah bayar → bukan estimasi
        $this->customer($officer);           // belum bayar → estimasi
        Customer::factory()->terminated()->create([
            'cluster_id' => $officer->clusters()->value('id'),
            'billing_amount' => 100_000,
        ]);

        $estimate = $this->service()
            ->commissionQuery(now()->startOfMonth())
            ->find($officer->getKey())
            ->estimated_commission_amount;

        $this->assertSame(4_000.0, $estimate);
        $this->assertSame(4_000.0, $this->service()->commissionEstimateTotal(now()->startOfMonth()));
    }

    public function testPeriodFilterChangesTheNumbers(): void
    {
        $officer = $this->officer(4000);
        $customer = $this->customer($officer);
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'period' => now()->subMonth()->startOfMonth(),
            'billed_amount' => 100_000,
            'paid_amount' => 100_000,
        ]);

        $this->assertSame(0.0, $this->service()->commissionTotal(now()->startOfMonth()));
        $this->assertSame(4_000.0, $this->service()->commissionTotal(now()->subMonth()->startOfMonth()));

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CommissionSummary::class, ['pageFilters' => ['period' => now()->format('Y-m')]])
            ->assertSee('Rp 0');
        Livewire::test(CommissionSummary::class, ['pageFilters' => ['period' => now()->subMonth()->format('Y-m')]])
            ->assertSee('Rp 4.000');
    }

    public function testSummaryShowsTotalsAndOfficerCount(): void
    {
        $officer = $this->officer(4000);
        $this->customer($officer, 'cash');
        $this->customer($officer);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CommissionSummary::class, ['pageFilters' => ['period' => now()->format('Y-m')]])
            ->assertSee('Rp 4.000')
            ->assertSee('1');
    }

    public function testFilterRendersAboveSummaryAndTable(): void
    {
        $officer = $this->officer();
        $officer->update(['name' => 'Petugas Komisi']);
        $this->customer($officer, 'cash');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Commission::class)
            ->assertSeeInOrder(['filters.period', 'CommissionSummary', 'Petugas Komisi'], escape: false);
    }

    public function testExportStreamsXlsxOfFilteredRows(): void
    {
        $officer = $this->officer();
        $this->customer($officer, 'cash');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Commission::class)
            ->callAction('export')
            ->assertFileDownloaded();
    }
}
