<?php

namespace Tests\Feature;

use App\Exports\ArrearsExport;
use App\Exports\ClusterReportExport;
use App\Exports\CommissionReportExport;
use App\Exports\CustomerGrowthExport;
use App\Exports\MonthlyRecapExport;
use App\Exports\OfficerDepositReportExport;
use App\Filament\Pages\ArrearsReport;
use App\Filament\Pages\ClusterReport;
use App\Filament\Pages\CommissionReport;
use App\Filament\Pages\CustomerGrowthReport;
use App\Filament\Pages\OfficerDepositReport;
use App\Filament\Pages\PaymentRecapReport;
use App\Models\Cluster;
use App\Models\CommissionRecipient;
use App\Models\Customer;
use App\Models\OfficerDeposit;
use App\Models\Package;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReportPageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<class-string> */
    private static function pages(): array
    {
        return [
            PaymentRecapReport::class,
            OfficerDepositReport::class,
            ArrearsReport::class,
            CommissionReport::class,
            ClusterReport::class,
            CustomerGrowthReport::class,
        ];
    }

    public function testReportPagesRenderExportButton(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        // Regresi: slot tombol sempat salah nama sehingga tidak pernah ter-render.
        foreach ([OfficerDepositReport::class, ArrearsReport::class, CommissionReport::class] as $page) {
            Livewire::test($page)->assertSee(__('Download Excel'));
        }
    }

    public function testRecapReportHasNoExcelExport(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Rekap Pembayaran sengaja view-only.
        Livewire::test(PaymentRecapReport::class)
            ->assertDontSee(__('Download Excel'))
            ->call('download')
            ->assertNoFileDownloaded();

        // Tetap ditutup di server, bukan cuma tombolnya disembunyikan.
        $this->expectException(HttpException::class);
        (new PaymentRecapReport)->download();
    }

    public function testNewReportsDownloadXlsx(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Transaction::factory()->create(['paid_amount' => 1_000_000]);

        $suffix = now()->startOfMonth()->toDateString().'_'.now()->endOfMonth()->toDateString();

        foreach ([
            CommissionReport::class => 'komisi',
            ClusterReport::class => 'cluster',
            CustomerGrowthReport::class => 'pelanggan',
        ] as $page => $prefix) {
            Livewire::test($page)
                ->call('download')
                ->assertFileDownloaded("{$prefix}_{$suffix}.xlsx");
        }
    }

    public function testRecapNumbersMatchBillingService(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Transaction::factory()->create(['paid_amount' => 750_000]);
        Transaction::factory()->transfer()->create(['paid_amount' => 250_000]);

        $summary = app(BillingService::class)->rangeSummary(now()->startOfMonth(), now()->endOfMonth());
        $rows = (new MonthlyRecapExport(now()->startOfMonth(), now()->endOfMonth()))->rows();
        $flat = collect($rows)->flatten();

        // Angka rekap HARUS bersumber BillingService (konsisten dashboard).
        $this->assertTrue($flat->contains(fn ($v) => str_contains((string) $v, '750.000')));
        $this->assertTrue($flat->contains(fn ($v) => str_contains((string) $v, '250.000')));
        $this->assertSame(750_000.0, $summary['cash']);
        // Kolom pembanding "Last Month" sudah dihapus — heading 2 kolom.
        $this->assertSame([__('Metric'), __('Value')], $rows[0]);
    }

    public function testRecapPagePreviewsDataOnScreen(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Transaction::factory()->create(['paid_amount' => 750_000, 'paid_at' => now()]);

        // Preview on-screen pakai rows() yang sama dgn Excel.
        Livewire::test(PaymentRecapReport::class)
            ->assertSee(__('Cash Collected'))
            ->assertSee('750.000');
    }

    public function testRecapExcludesPaymentsOutsideRange(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Transaction::factory()->create(['paid_amount' => 750_000, 'paid_at' => now()]);
        Transaction::factory()->create(['paid_amount' => 999_000, 'paid_at' => now()->subMonths(2)]);

        $flat = collect((new MonthlyRecapExport(now()->startOfMonth(), now()->endOfMonth()))->rows())
            ->flatten()->implode('|');

        $this->assertStringContainsString('750.000', $flat);
        $this->assertStringNotContainsString('999.000', $flat);
    }

    public function testDepositReportFiltersHistoryByRange(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $officer = User::factory()->fieldOfficer()->create();

        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 350_000,
            'deposited_at' => now(),
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 888_000,
            'deposited_at' => now()->subMonths(2),
        ]);

        $flat = collect((new OfficerDepositReportExport(now()->startOfMonth(), now()->endOfMonth()))->rows())
            ->flatten()->implode('|');

        $this->assertStringContainsString('350.000', $flat);
        $this->assertStringNotContainsString('888.000', $flat);
    }

    public function testDepositReportPreviewsPerOfficerSummary(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $officer = User::factory()->fieldOfficer()->create(['name' => 'Petugas Uji']);
        Transaction::factory()->create([
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'paid_amount' => 600_000,
            'paid_at' => now(),
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 200_000,
            'deposited_at' => now(),
        ]);

        // Preview ringkasan per-petugas: sisa = 600rb − 200rb = 400rb.
        Livewire::test(OfficerDepositReport::class)
            ->assertSee('Petugas Uji')
            ->assertSee('400.000');
    }

    public function testArrearsExportContainsAllUnpaidRows(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $unpaid = Customer::factory()->create(['name' => 'Nunggak Satu']);
        $isolir = Customer::factory()->suspended()->create(['name' => 'Isolir Satu']);
        $paid = Customer::factory()->create(['name' => 'Sudah Lunas']);
        Transaction::factory()->create([
            'customer_id' => $paid->id,
            'billed_amount' => 100_000,
            'paid_amount' => 100_000,
        ]);

        $rows = (new ArrearsExport(now()->startOfMonth(), now()->endOfMonth()))->rows();
        $flat = collect($rows)->flatten()->implode('|');

        $this->assertStringContainsString('Nunggak Satu', $flat);
        $this->assertStringContainsString('Isolir Satu', $flat);
        $this->assertStringNotContainsString('Sudah Lunas', $flat);
        // Heading + 2 baris tunggakan.
        $this->assertCount(3, $rows);
    }

    public function testCommissionReportSumsPaidReferralsInRange(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $recipient = CommissionRecipient::factory()->create([
            'name' => 'Referal Satu',
            'commission_percent' => 10,
        ]);
        $customer = Customer::factory()->create(['referral_id' => $recipient->id]);

        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'billed_amount' => 500_000,
            'paid_amount' => 500_000,
            'paid_at' => now(),
        ]);
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'period' => now()->subMonths(3)->startOfMonth(),
            'billed_amount' => 900_000,
            'paid_amount' => 900_000,
            'paid_at' => now()->subMonths(3),
        ]);

        $rows = (new CommissionReportExport(now()->startOfMonth(), now()->endOfMonth()))->rows();
        $row = collect($rows)->firstWhere(0, 'Referal Satu');

        $this->assertNotNull($row);
        $this->assertSame(1, $row[4]);
        // Basis 500rb (transaksi di luar rentang tidak ikut) × 10% = 50rb.
        $this->assertStringContainsString('500.000', (string) $row[5]);
        $this->assertStringContainsString('50.000', (string) $row[6]);
        $this->assertStringNotContainsString('900.000', collect($rows)->flatten()->implode('|'));
    }

    public function testCommissionReportKeepsRecipientsWithoutTransactions(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        CommissionRecipient::factory()->create(['name' => 'Belum Dapat']);

        $rows = (new CommissionReportExport(now()->startOfMonth(), now()->endOfMonth()))->rows();
        $row = collect($rows)->firstWhere(0, 'Belum Dapat');

        $this->assertNotNull($row);
        $this->assertSame(0, $row[4]);
    }

    public function testClusterReportShowsPerClusterCollection(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $officer = User::factory()->fieldOfficer()->create(['name' => 'Pak Petugas']);
        $cluster = Cluster::factory()->create(['name' => 'Cluster Melati', 'officer_id' => $officer->id]);

        $lunas = Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 100_000]);
        Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 100_000]);
        Transaction::factory()->create([
            'customer_id' => $lunas->id,
            'billed_amount' => 100_000,
            'paid_amount' => 100_000,
            'paid_at' => now(),
        ]);

        $rows = (new ClusterReportExport(now()->startOfMonth(), now()->endOfMonth()))->rows();
        $row = collect($rows)->firstWhere(0, 'Cluster Melati');

        $this->assertNotNull($row);
        $this->assertSame('Pak Petugas', $row[1]);
        $this->assertSame(2, $row[2]);
        // Tagihan 200rb, tertagih 100rb → 50%, 1 pelanggan menunggak.
        $this->assertStringContainsString('200.000', (string) $row[3]);
        $this->assertStringContainsString('100.000', (string) $row[4]);
        $this->assertSame('50%', $row[5]);
        $this->assertSame(1, $row[6]);
    }

    public function testCustomerGrowthReportCountsMovementPerMonth(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $package = Package::factory()->create(['name' => 'Paket Uji']);

        Customer::factory()->create([
            'registered_at' => now()->startOfMonth(),
            'package_id' => $package->id,
        ]);
        Customer::factory()->create(['registered_at' => now()->subMonths(3)]);
        Customer::factory()->suspended()->create([
            'registered_at' => now()->subMonths(3),
            'suspended_at' => now(),
        ]);
        Customer::factory()->terminated()->create([
            'registered_at' => now()->subMonths(3),
            'terminated_at' => now(),
        ]);

        $rows = (new CustomerGrowthExport(now()->startOfMonth(), now()->endOfMonth()))->rows();
        $month = collect($rows)->firstWhere(0, now()->translatedFormat('F Y'));

        // Bulan ini: 1 baru, 1 isolir, 1 berhenti, total terdaftar kumulatif 4.
        $this->assertSame([now()->translatedFormat('F Y'), 1, 1, 1, 4], $month);
        // Blok kedua (per paket) hanya ada di Excel.
        $this->assertStringContainsString('Paket Uji', collect($rows)->flatten()->implode('|'));
    }

    public function testCustomerGrowthPreviewShowsOnlyMonthlyRows(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Package::factory()->create(['name' => 'Paket Uji']);

        // Preview on-screen harus kolom seragam — blok paket dibuang.
        Livewire::test(CustomerGrowthReport::class)
            ->assertSee(now()->translatedFormat('F Y'))
            ->assertDontSee('Paket Uji');
    }

    public function testReportTableIsPaginated(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $cluster = Cluster::factory()->create();
        $package = Package::factory()->create();
        Customer::factory()->count(30)->create([
            'cluster_id' => $cluster->id,
            'package_id' => $package->id,
        ]);

        $records = Livewire::test(ArrearsReport::class)->instance()->getTableRecords();

        // Tanpa paginator, records() array menumpahkan seluruh baris ke satu halaman.
        $this->assertInstanceOf(LengthAwarePaginator::class, $records);
        $this->assertSame(30, $records->total());
        $this->assertCount(25, $records->items());
    }

    public function testReportTableSecondPageShowsRemainingRows(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $cluster = Cluster::factory()->create();
        $package = Package::factory()->create();
        Customer::factory()->count(30)->create([
            'cluster_id' => $cluster->id,
            'package_id' => $package->id,
        ]);

        $records = Livewire::test(ArrearsReport::class)
            ->set('paginators.page', 2)
            ->instance()
            ->getTableRecords();

        $this->assertCount(5, $records->items());
        // Key baris harus unik lintas halaman, bukan 0..24 berulang.
        $this->assertSame([25, 26, 27, 28, 29], array_keys($records->items()));
    }

    public function testFixedSizeReportsAreNotPaginated(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        User::factory()->fieldOfficer()->count(3)->create();

        // Rekap (8 metrik) & Setoran (1 baris per petugas) selalu muat satu layar.
        foreach ([PaymentRecapReport::class, OfficerDepositReport::class] as $page) {
            $records = Livewire::test($page)->instance()->getTableRecords();

            $this->assertNotInstanceOf(LengthAwarePaginator::class, $records);
        }

        $this->assertCount(8, Livewire::test(PaymentRecapReport::class)->instance()->getTableRecords());
        $this->assertCount(3, Livewire::test(OfficerDepositReport::class)->instance()->getTableRecords());
    }

    public function testReportTableHandlesShowAllOnEmptyData(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // perPage "all" tanpa data: paginator membagi dengan perPage → jangan 0.
        $records = Livewire::test(ArrearsReport::class)
            ->set('tableRecordsPerPage', 'all')
            ->instance()
            ->getTableRecords();

        $this->assertSame(0, $records->total());
    }

    public function testPetugasCannotAccessReportPages(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        foreach (self::pages() as $page) {
            $this->get($page::getUrl())->assertForbidden();
        }
    }

    public function testAdminCanAccessReportPages(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (self::pages() as $page) {
            $this->get($page::getUrl())->assertOk();
        }
    }
}
