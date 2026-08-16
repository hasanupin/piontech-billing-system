<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Filament\Pages\Commission;
use App\Filament\Widgets\CommissionSummary;
use App\Models\CommissionRecipient;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommissionPageTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BillingService
    {
        return app(BillingService::class);
    }

    /** Komisi satu penerima pada periode berjalan. */
    private function commissionOf(CommissionRecipient $recipient): float
    {
        return $this->service()
            ->commissionQuery(now()->startOfMonth())
            ->find($recipient->getKey())
            ->commission_amount;
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

    public function testShowsAllRecipientsWithZeroWhenNoPaidTransaction(): void
    {
        $recipients = CommissionRecipient::factory()->count(2)->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Commission::class)
            ->assertCanSeeTableRecords($recipients);

        foreach ($recipients as $recipient) {
            $this->assertSame(0.0, $this->commissionOf($recipient));
        }
    }

    public function testAccumulatesCommissionAcrossReferredCustomers(): void
    {
        $recipient = CommissionRecipient::factory()->create(['commission_percent' => 4]);

        foreach ([110_000, 100_000] as $amount) {
            $customer = Customer::factory()->create(['referral_id' => $recipient->id]);
            Transaction::factory()->create([
                'customer_id' => $customer->id,
                'period' => now()->startOfMonth(),
                'billed_amount' => $amount,
                'paid_amount' => $amount,
            ]);
        }

        // Pelanggan tanpa referal tidak menambah komisi siapa pun.
        Transaction::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'period' => now()->startOfMonth(),
            'billed_amount' => 999_000,
            'paid_amount' => 999_000,
        ]);

        $this->assertSame(8_400.0, $this->commissionOf($recipient));
    }

    public function testIgnoresUnpaidPartialAndOtherPeriodTransactions(): void
    {
        $recipient = CommissionRecipient::factory()->create(['commission_percent' => 10]);
        $customer = Customer::factory()->create(['referral_id' => $recipient->id]);

        // Sebagian → status partial.
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'period' => now()->startOfMonth(),
            'billed_amount' => 100_000,
            'paid_amount' => 40_000,
        ]);
        // Lunas tapi bulan lain.
        Transaction::factory()->create([
            'customer_id' => Customer::factory()->create(['referral_id' => $recipient->id])->id,
            'period' => now()->startOfMonth()->subMonth(),
            'billed_amount' => 100_000,
            'paid_amount' => 100_000,
        ]);

        $this->assertSame(0.0, $this->commissionOf($recipient));
    }

    public function testUsesPercentagePerRecipient(): void
    {
        $small = CommissionRecipient::factory()->create(['commission_percent' => 4]);
        $big = CommissionRecipient::factory()->create(['commission_percent' => 10]);

        foreach ([$small, $big] as $recipient) {
            $customer = Customer::factory()->create(['referral_id' => $recipient->id]);
            Transaction::factory()->create([
                'customer_id' => $customer->id,
                'period' => now()->startOfMonth(),
                'billed_amount' => 100_000,
                'paid_amount' => 100_000,
            ]);
        }

        $this->assertSame(4_000.0, $this->commissionOf($small));
        $this->assertSame(10_000.0, $this->commissionOf($big));
    }

    public function testCommissionTotalSumsAllRecipients(): void
    {
        foreach ([4, 10] as $percent) {
            $recipient = CommissionRecipient::factory()->create(['commission_percent' => $percent]);
            $customer = Customer::factory()->create(['referral_id' => $recipient->id]);
            Transaction::factory()->create([
                'customer_id' => $customer->id,
                'period' => now()->startOfMonth(),
                'billed_amount' => 100_000,
                'paid_amount' => 100_000,
            ]);
        }

        $this->assertSame(14_000.0, $this->service()->commissionTotal(now()->startOfMonth()));
    }

    public function testPeriodFilterChangesTheNumbers(): void
    {
        $recipient = CommissionRecipient::factory()->create(['commission_percent' => 10]);
        $customer = Customer::factory()->create(['referral_id' => $recipient->id]);
        // startOfMonth dulu: subMonth pada tanggal 31 melompat balik ke bulan yang sama.
        $lastMonth = now()->startOfMonth()->subMonth();
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'period' => $lastMonth,
            'billed_amount' => 100_000,
            'paid_amount' => 100_000,
        ]);
        $this->actingAs(User::factory()->admin()->create());

        $this->assertSame(0.0, $this->commissionOf($recipient));
        $this->assertSame(
            10_000.0,
            $this->service()->commissionQuery($lastMonth)->find($recipient->getKey())->commission_amount,
        );

        // Kartu ringkasan mengikuti filter periode halaman.
        Livewire::test(CommissionSummary::class, ['pageFilters' => ['period' => now()->format('Y-m')]])
            ->assertSee('Rp 0');
        Livewire::test(CommissionSummary::class, ['pageFilters' => ['period' => $lastMonth->format('Y-m')]])
            ->assertSee('Rp 10.000');
    }

    public function testFilterRendersAboveSummaryAndTable(): void
    {
        CommissionRecipient::factory()->create(['name' => 'Pak Referal']);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(Commission::getUrl())->assertSeeInOrder([
            'filters.period',    // filter halaman
            'CommissionSummary', // kartu ringkasan
            'Pak Referal',       // baris tabel
        ], escape: false);
    }

    /** Estimasi = tagihan pelanggan referal yang BELUM lunas × persentase penerima. */
    public function testEstimateCountsOnlyUnpaidBillableReferredCustomers(): void
    {
        $recipient = CommissionRecipient::factory()->create(['commission_percent' => 10]);

        // Belum bayar → masuk estimasi.
        Customer::factory()->create([
            'referral_id' => $recipient->id,
            'billing_amount' => 100_000,
        ]);
        // Sudah lunas periode ini → tidak masuk estimasi (sudah jadi komisi riil).
        $paid = Customer::factory()->create([
            'referral_id' => $recipient->id,
            'billing_amount' => 200_000,
        ]);
        Transaction::factory()->create([
            'customer_id' => $paid->id,
            'period' => now()->startOfMonth(),
            'billed_amount' => 200_000,
            'paid_amount' => 200_000,
        ]);
        // Berhenti berlangganan → tidak ditagih, tidak masuk estimasi.
        Customer::factory()->create([
            'referral_id' => $recipient->id,
            'billing_amount' => 500_000,
            'status' => CustomerStatus::Terminated,
        ]);
        // Tanpa referal → tidak menambah estimasi siapa pun.
        Customer::factory()->create(['billing_amount' => 900_000]);

        $this->assertSame(
            10_000.0,
            $this->service()->commissionQuery(now()->startOfMonth())
                ->find($recipient->getKey())
                ->estimated_commission_amount,
        );
        $this->assertSame(10_000.0, $this->service()->commissionEstimateTotal(now()->startOfMonth()));
    }

    public function testSummaryShowsTotalsAndRecipientCounts(): void
    {
        $recipient = CommissionRecipient::factory()->create(['commission_percent' => 10]);
        $customer = Customer::factory()->create([
            'referral_id' => $recipient->id,
            'billing_amount' => 100_000,
        ]);
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'period' => now()->startOfMonth(),
            'billed_amount' => 100_000,
            'paid_amount' => 100_000,
        ]);
        // Belum bayar → estimasi 20.000.
        Customer::factory()->create([
            'referral_id' => $recipient->id,
            'billing_amount' => 200_000,
        ]);
        CommissionRecipient::factory()->customerType()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CommissionSummary::class, [
            'pageFilters' => ['period' => now()->format('Y-m')],
        ])
            ->assertSuccessful()
            ->assertSee('Rp 10.000')  // total komisi
            ->assertSee('Rp 20.000')  // estimasi komisi
            ->assertSee('1 / 1');     // non pelanggan / pelanggan
    }

    public function testExportStreamsXlsxOfFilteredRows(): void
    {
        CommissionRecipient::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Commission::class)
            ->callAction('export')
            ->assertFileDownloaded();
    }
}
