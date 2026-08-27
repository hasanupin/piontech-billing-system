<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Filament\Pages\MonthlyBilling;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanAccessTransactionResource(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(TransactionResource::getUrl('index'))->assertOk();
    }

    /** Petugas mencatat transaksi lewat panel mobile — lihat FieldPanelTest. */
    public function testFieldOfficerCannotAccessAdminTransactionResource(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(TransactionResource::getUrl('index'))->assertForbidden();
    }

    public function testPrefillsNominalFromCustomerBillingAmount(): void
    {
        $customer = Customer::factory()->create(['billing_amount' => 110000]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateTransaction::class)
            ->set('data.customer_id', $customer->id)
            ->assertSet('data.billed_amount', '110.000,00');
    }

    public function testPrefillsFromCustomerIdQueryString(): void
    {
        $customer = Customer::factory()->create(['billing_amount' => 115000]);
        $this->actingAs(User::factory()->admin()->create());

        // Tombol "Catat Pembayaran" di Tagihan Bulanan mengirim ?customer_id=...
        Livewire::withQueryParams(['customer_id' => $customer->id])
            ->test(CreateTransaction::class)
            ->assertSet('data.customer_id', $customer->id)
            ->assertSet('data.billed_amount', '115.000,00')
            // Nominal bayar ikut terisi — mayoritas pembayaran lunas penuh.
            ->assertSet('data.paid_amount', '115.000,00')
            // Default field lain tidak boleh hilang gara-gara prefill.
            ->assertSet('data.payment_method', PaymentMethod::Cash->value)
            ->assertSet('data.period', now()->startOfMonth()->format('Y-m-d'))
            ->assertNotSet('data.paid_at', null);

        // Jalur HTTP asli (link dari Tagihan Bulanan) tetap sehat.
        $this->get(TransactionResource::getUrl('create', ['customer_id' => $customer->id]))
            ->assertOk();
    }

    public function testPrefillsFromQueryStringForFieldOfficerOwnCluster(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $customer = Customer::factory()->create([
            'billing_amount' => 100000,
            'cluster_id' => Cluster::factory()->create(['officer_id' => $officer->id])->id,
        ]);

        $this->actingAs($officer);

        Livewire::withQueryParams(['customer_id' => $customer->id])
            ->test(CreateTransaction::class)
            ->assertSet('data.customer_id', $customer->id)
            ->assertSet('data.billed_amount', '100.000,00')
            ->assertSet('data.paid_amount', '100.000,00')
            // Petugas terkunci ke dirinya sendiri — default ini juga harus selamat.
            ->assertSet('data.officer_id', $officer->id);
    }

    public function testIgnoresCustomerIdOutsideFieldOfficerScope(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $foreign = Customer::factory()->create([
            'billing_amount' => 100000,
            'cluster_id' => Cluster::factory()->create()->id,
        ]);

        $this->actingAs($officer);

        // Pelanggan cluster lain tidak boleh bocor lewat query string.
        Livewire::withQueryParams(['customer_id' => $foreign->id])
            ->test(CreateTransaction::class)
            ->assertSet('data.customer_id', null)
            ->assertSet('data.billed_amount', null);
    }

    public function testHidesCreateAnotherWhenPrefilledFromCustomer(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        // Datang dari "Catat Pembayaran" = mencatat satu tagihan spesifik,
        // bukan entri massal — "Buat & buat lainnya" tidak relevan.
        $this->assertFalse(
            Livewire::withQueryParams(['customer_id' => $customer->id])
                ->test(CreateTransaction::class)
                ->instance()
                ->canCreateAnother(),
        );
    }

    public function testHidesCreateAnotherOnBlankCreate(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Pilihan "lanjut atau berhenti" dipindah ke popup setelah simpan.
        $this->assertFalse(
            Livewire::test(CreateTransaction::class)->instance()->canCreateAnother(),
        );
    }

    public function testRedirectsBackToMonthlyBillingAfterPrefilledCreate(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $customer = Customer::factory()->create(['billing_amount' => 115000]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::withQueryParams(['customer_id' => $customer->id])
            ->test(CreateTransaction::class)
            ->fillForm(['officer_id' => $officer->id])
            ->call('create')
            ->assertHasNoFormErrors()
            // Alur "Catat Pembayaran" mencatat satu tagihan spesifik — tanpa popup.
            ->assertActionNotMounted()
            ->assertRedirect(MonthlyBilling::getUrl());
    }

    public function testShowsPromptModalAfterBlankCreate(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->createBlankTransaction()
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertActionMounted('createdPrompt');

        // Record tetap tersimpan walau redirect ditahan.
        $this->assertSame(1, Transaction::count());
    }

    public function testPromptBackToListRedirectsToPinnedList(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $test = $this->createBlankTransaction()->callMountedAction();

        $test->assertRedirect(TransactionResource::getUrl('index', [
            'created' => Transaction::first()->getKey(),
        ]));
    }

    public function testPromptCreateAnotherRedirectsToBlankForm(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->createBlankTransaction()
            ->callMountedAction(['another' => true])
            ->assertRedirect(TransactionResource::getUrl('create'));
    }

    public function testPinsCreatedRecordToFirstRow(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Urutan default paid_at desc menaruh $newest di atas; pin harus menang.
        $newest = Transaction::factory()->create([
            'period' => now()->startOfMonth(),
            'paid_at' => now(),
        ]);
        $pinned = Transaction::factory()->create([
            'period' => now()->startOfMonth(),
            'paid_at' => now()->subDay(),
        ]);

        Livewire::withQueryParams(['created' => $pinned->getKey()])
            ->test(ListTransactions::class)
            ->assertCanSeeTableRecords([$pinned, $newest], inOrder: true);
    }

    /** Simpan satu transaksi lewat form kosong (tanpa ?customer_id=...). */
    private function createBlankTransaction(): Testable
    {
        $officer = User::factory()->fieldOfficer()->create();
        $customer = Customer::factory()->create();

        return Livewire::test(CreateTransaction::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'payment_method' => PaymentMethod::Cash->value,
                'officer_id' => $officer->id,
                'billed_amount' => '100000',
                'paid_amount' => '100000',
            ])
            ->call('create');
    }

    public function testPrefillsPaidAmountWhenCustomerSelectedManually(): void
    {
        $customer = Customer::factory()->create(['billing_amount' => 110000]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateTransaction::class)
            ->set('data.customer_id', $customer->id)
            ->assertSet('data.paid_amount', '110.000,00');
    }

    public function testAllowsOverridingPrefilledNominal(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $package = Package::factory()->create(['default_price' => 110000]);
        $customer = Customer::factory()->create(['package_id' => $package->id]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'payment_method' => PaymentMethod::Cash->value,
                'officer_id' => $officer->id,
                'billed_amount' => '100000',
                'paid_amount' => '100000',
            ])
            ->call('create')
            // assertHasNoFormErrors() tidak bisa dipakai setelah popup pasca-simpan
            // termount: helper-nya mencari schema milik action, yang memang tidak ada.
            ->assertHasNoErrors();

        $this->assertSame('100000.00', Transaction::first()->billed_amount);
    }

    public function testFieldOfficerCanChooseTransfer(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        Cluster::factory()->create(['officer_id' => $officer->id]);
        $this->actingAs($officer);

        Livewire::test(CreateTransaction::class)
            ->assertFormFieldExists('payment_method', function ($field): bool {
                return array_keys($field->getOptions())
                    === [PaymentMethod::Cash->value, PaymentMethod::Transfer->value];
            });
    }

    public function testTransferTransactionHasNullOfficerAndExcludedFromHeldByOfficers(): void
    {
        Transaction::factory()->transfer()->create(['paid_amount' => 500000]);

        $summary = app(BillingService::class)->monthlySummary(now());

        $this->assertSame(0.0, $summary['held_by_officers']); // transfer tidak lewat petugas
        $this->assertSame(500000.0, $summary['transfer']);
    }

    public function testPreventsDuplicateTransactionSameCustomerSamePeriod(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $customer = Customer::factory()->create();
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'period' => now()->startOfMonth(),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'payment_method' => PaymentMethod::Cash->value,
                'officer_id' => $officer->id,
                'billed_amount' => '100000',
                'paid_amount' => '100000',
            ])
            ->call('create')
            ->assertHasFormErrors(['customer_id']);
    }

    public function testMonthlyBillingPageShowsBillableCustomersAndUnpaidFilter(): void
    {
        $paidCustomer = Customer::factory()->create();
        Transaction::factory()->create([
            'customer_id' => $paidCustomer->id,
            'period' => now()->startOfMonth(),
            'status' => 'paid',
        ]);
        $unpaidCustomer = Customer::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MonthlyBilling::class)
            ->assertCanSeeTableRecords([$paidCustomer, $unpaidCustomer])
            ->set('filters.payment_status', 'unpaid')
            ->assertCanSeeTableRecords([$unpaidCustomer])
            ->assertCanNotSeeTableRecords([$paidCustomer]);
    }

    public function testListDefaultsToCurrentMonthTransactions(): void
    {
        $thisMonth = Transaction::factory()->create();
        $lastMonth = Transaction::factory()->create([
            'period' => now()->subMonthNoOverflow()->startOfMonth(),
            'paid_at' => now()->subMonthNoOverflow(),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        // Tanpa menyentuh filter, list hanya menampilkan bulan berjalan.
        Livewire::test(ListTransactions::class)
            ->assertCanSeeTableRecords([$thisMonth])
            ->assertCanNotSeeTableRecords([$lastMonth]);
    }

    public function testPaymentMethodDropdownFiltersTransactions(): void
    {
        $cash = Transaction::factory()->create();
        $transfer = Transaction::factory()->transfer()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListTransactions::class)
            ->filterTable('payment_method', PaymentMethod::Transfer->value)
            ->assertCanSeeTableRecords([$transfer])
            ->assertCanNotSeeTableRecords([$cash]);
    }

    public function testCanSwitchFromPeriodToDateRangeFilter(): void
    {
        $thisMonth = Transaction::factory()->create();
        $lastMonth = Transaction::factory()->create([
            'period' => now()->subMonthNoOverflow()->startOfMonth(),
            'paid_at' => now()->subMonthNoOverflow(),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        // Centang "gunakan rentang tanggal" → periode diabaikan, paid_at yang dipakai.
        Livewire::test(ListTransactions::class)
            ->filterTable('period', [
                'use_range' => true,
                'from' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'until' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ])
            ->assertCanSeeTableRecords([$lastMonth])
            ->assertCanNotSeeTableRecords([$thisMonth]);
    }

    public function testClusterDropdownFiltersTransactions(): void
    {
        $cluster = Cluster::factory()->create();
        $inCluster = Transaction::factory()->create([
            'customer_id' => Customer::factory()->create(['cluster_id' => $cluster->id])->id,
        ]);
        $outside = Transaction::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListTransactions::class)
            ->filterTable('cluster', $cluster->id)
            ->assertCanSeeTableRecords([$inCluster])
            ->assertCanNotSeeTableRecords([$outside]);
    }

    public function testOfficerDropdownFiltersTransactions(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $mine = Transaction::factory()->create(['officer_id' => $officer->id]);
        $others = Transaction::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListTransactions::class)
            ->filterTable('officer_id', $officer->id)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$others]);
    }

    public function testAdminCanExportTransactionsToExcel(): void
    {
        Transaction::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListTransactions::class)
            ->callAction('export')
            ->assertFileDownloaded();
    }

    public function testFieldOfficerCannotExportTransactions(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        Livewire::test(ListTransactions::class)
            ->assertActionHidden('export');
    }
}
