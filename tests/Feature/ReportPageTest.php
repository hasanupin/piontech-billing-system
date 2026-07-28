<?php

namespace Tests\Feature;

use App\Exports\ArrearsExport;
use App\Exports\MonthlyRecapExport;
use App\Exports\OfficerDepositReportExport;
use App\Filament\Pages\ArrearsReport;
use App\Filament\Pages\OfficerDepositReport;
use App\Filament\Pages\PaymentRecapReport;
use App\Models\Customer;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportPageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<class-string> */
    private static function pages(): array
    {
        return [PaymentRecapReport::class, OfficerDepositReport::class, ArrearsReport::class];
    }

    public function testExcelRecapDownloadsWithData(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Transaction::factory()->create(['paid_amount' => 1_000_000]);

        $suffix = now()->startOfMonth()->toDateString().'_'.now()->endOfMonth()->toDateString();

        Livewire::test(PaymentRecapReport::class)
            ->call('download')
            ->assertFileDownloaded("rekap_{$suffix}.xlsx");
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
