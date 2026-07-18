<?php

namespace Tests\Feature;

use App\Exports\ArrearsExport;
use App\Exports\MonthlyRecapExport;
use App\Filament\Pages\ReportPage;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportPageTest extends TestCase
{
    use RefreshDatabase;

    public function testExcelRecapDownloadsWithData(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Transaction::factory()->create(['paid_amount' => 1_000_000]);

        Livewire::test(ReportPage::class)
            ->call('download', 'recap')
            ->assertFileDownloaded('rekap-'.now()->format('Y-m').'.xlsx');
    }

    public function testRecapNumbersMatchBillingService(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Transaction::factory()->create(['paid_amount' => 750_000]);
        Transaction::factory()->transfer()->create(['paid_amount' => 250_000]);

        $summary = app(BillingService::class)->monthlySummary(now());
        $flat = collect((new MonthlyRecapExport(now()->startOfMonth()))->rows())->flatten();

        // Angka rekap HARUS bersumber BillingService (konsisten dashboard).
        $this->assertTrue($flat->contains(fn ($v) => str_contains((string) $v, '750.000')));
        $this->assertTrue($flat->contains(fn ($v) => str_contains((string) $v, '250.000')));
        $this->assertSame(750_000.0, $summary['cash']);
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

        $rows = (new ArrearsExport(now()->startOfMonth()))->rows();
        $flat = collect($rows)->flatten()->implode('|');

        $this->assertStringContainsString('Nunggak Satu', $flat);
        $this->assertStringContainsString('Isolir Satu', $flat);
        $this->assertStringNotContainsString('Sudah Lunas', $flat);
        // Heading + 2 baris tunggakan.
        $this->assertCount(3, $rows);
    }

    public function testPetugasCannotAccessReportPage(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(ReportPage::getUrl())->assertForbidden();
    }

    public function testAdminCanAccessReportPage(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(ReportPage::getUrl())->assertOk();
    }
}
