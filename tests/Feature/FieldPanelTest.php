<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Filament\Auth\Login;
use App\Filament\Field\Pages\Dashboard as FieldDashboard;
use App\Filament\Field\Pages\Settings as FieldSettings;
use App\Filament\Field\Pages\Transactions as FieldTransactions;
use App\Filament\Field\Resources\Customers\FieldCustomerResource;
use App\Filament\Field\Resources\Customers\Pages\ListFieldCustomers;
use App\Filament\Field\Resources\Transactions\FieldTransactionResource;
use App\Filament\Field\Resources\Transactions\Pages\CreateFieldTransaction;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FieldPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('field');
    }

    private function officerWithCluster(): array
    {
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);

        return [$officer, $cluster];
    }

    // --- Akses panel ---

    public function testFieldOfficerCanAccessFieldPanel(): void
    {
        [$officer] = $this->officerWithCluster();

        $this->actingAs($officer)->get('/petugas')->assertOk();
    }

    public function testAdminAndSuperAdminAreForbiddenFromFieldPanel(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/petugas')->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/petugas')->assertForbidden();
    }

    public function testFieldOfficerIsForbiddenFromAdminPanel(): void
    {
        [$officer] = $this->officerWithCluster();

        $this->actingAs($officer)->get('/admin')->assertForbidden();
    }

    /** Satu halaman login untuk semua role — tidak ada login terpisah di /petugas. */
    public function testGuestIsRedirectedToTheSharedLogin(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        // Panel /petugas tidak punya halaman login sendiri, jadi Laravel jatuh
        // ke route bernama 'login' yang mengarah ke halaman yang sama.
        $this->get('/petugas')->assertRedirect('/login');
        $this->get('/login')->assertRedirect('/admin/login');
        // Tautan lama /petugas/login tetap mendarat di halaman yang sama.
        $this->get('/petugas/login')->assertRedirect('/admin/login');
    }

    public function testSharedLoginSendsOfficerToFieldPanelAndAdminToAdminPanel(): void
    {
        [$officer] = $this->officerWithCluster();
        $admin = User::factory()->admin()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $officer->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect('/petugas');

        auth()->logout();

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect('/admin');
    }

    public function testSharedLoginHonoursDeepLinkOnlyWithinTheTargetPanel(): void
    {
        [$officer] = $this->officerWithCluster();

        // Bounce dari halaman dalam panel field → kembali ke halaman itu.
        session(['url.intended' => url('/petugas/pengaturan')]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $officer->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect('/petugas/pengaturan');

        auth()->logout();

        // Tautan sisa dari panel lain diabaikan: kalau diikuti, petugas akan
        // kena 403 tepat setelah login berhasil.
        session(['url.intended' => url('/admin/users')]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $officer->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect('/petugas');
    }

    public function testSharedLoginStillRejectsInactiveOfficer(): void
    {
        $officer = User::factory()->fieldOfficer()->inactive()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $officer->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function testInactiveOfficerIsForbidden(): void
    {
        $officer = User::factory()->fieldOfficer()->inactive()->create();

        $this->actingAs($officer)->get('/petugas')->assertForbidden();
    }

    public function testRootRedirectsByRole(): void
    {
        [$officer] = $this->officerWithCluster();

        $this->actingAs($officer)->get('/')->assertRedirect('/petugas');
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertRedirect('/admin');
    }

    // --- Dashboard ---

    public function testDashboardShowsOwnDueTodayCustomersOnly(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $mine = Customer::factory()->create([
            'cluster_id' => $cluster->id,
            'billing_day' => now()->day,
        ]);
        $other = Customer::factory()->create(['billing_day' => now()->day]);
        $this->actingAs($officer);

        Livewire::test(FieldDashboard::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function testDashboardCollectActionLinksToCreateTransaction(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $customer = Customer::factory()->create([
            'cluster_id' => $cluster->id,
            'billing_day' => now()->day,
        ]);
        $this->actingAs($officer);

        Livewire::test(FieldDashboard::class)
            ->assertTableActionHasUrl('collect', FieldTransactionResource::getUrl('create', [
                'customer_id' => $customer->getKey(),
            ]), record: $customer);
    }

    // --- Pelanggan ---

    public function testCustomerListIsScopedAndSearchableByName(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $budi = Customer::factory()->create(['cluster_id' => $cluster->id, 'name' => 'Budi Target']);
        $siti = Customer::factory()->create(['cluster_id' => $cluster->id, 'name' => 'Siti Lain']);
        $other = Customer::factory()->create(['name' => 'Cluster Lain']);
        $this->actingAs($officer);

        Livewire::test(ListFieldCustomers::class)
            ->assertCanSeeTableRecords([$budi, $siti])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('Budi')
            ->assertCanSeeTableRecords([$budi])
            ->assertCanNotSeeTableRecords([$siti]);
    }

    // --- Transaksi (daftar tagihan per periode) ---

    public function testTransactionsPageFiltersPaidAndUnpaid(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $paid = Customer::factory()->create(['cluster_id' => $cluster->id]);
        $unpaid = Customer::factory()->create(['cluster_id' => $cluster->id]);
        Transaction::factory()->create([
            'customer_id' => $paid->id,
            'officer_id' => $officer->id,
            'period' => now()->startOfMonth(),
        ]);
        $this->actingAs($officer);

        Livewire::test(FieldTransactions::class)
            ->set('filters.payment_status', 'paid')
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$unpaid])
            ->set('filters.payment_status', 'unpaid')
            ->assertCanSeeTableRecords([$unpaid])
            ->assertCanNotSeeTableRecords([$paid]);
    }

    public function testTransactionsPagePeriodFilterChangesStatus(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $customer = Customer::factory()->create(['cluster_id' => $cluster->id]);
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'officer_id' => $officer->id,
            'period' => now()->startOfMonth()->subMonth(),
        ]);
        $this->actingAs($officer);

        Livewire::test(FieldTransactions::class)
            ->set('filters.payment_status', 'unpaid')
            ->assertCanSeeTableRecords([$customer])
            ->set('filters.period', now()->startOfMonth()->subMonth()->format('Y-m'))
            ->set('filters.payment_status', 'paid')
            ->assertCanSeeTableRecords([$customer]);
    }

    public function testRecordPaymentUrlCarriesCustomerAndPeriod(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $customer = Customer::factory()->create(['cluster_id' => $cluster->id]);
        $period = now()->startOfMonth()->subMonth()->format('Y-m');
        $this->actingAs($officer);

        Livewire::test(FieldTransactions::class)
            ->set('filters.period', $period)
            ->assertTableActionHasUrl('record_payment', FieldTransactionResource::getUrl('create', [
                'customer_id' => $customer->getKey(),
                'period' => $period,
            ]), record: $customer);
    }

    public function testCreateTransactionPrefillsPeriodFromQueryString(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $customer = Customer::factory()->create(['cluster_id' => $cluster->id, 'billing_amount' => 150000]);
        $period = now()->startOfMonth()->subMonth();
        $this->actingAs($officer);

        Livewire::withQueryParams([
            'customer_id' => $customer->getKey(),
            'period' => $period->format('Y-m'),
        ])
            ->test(CreateFieldTransaction::class)
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transactions', [
            'customer_id' => $customer->getKey(),
            'officer_id' => $officer->id,
            'period' => $period->toDateTimeString(),
            'payment_method' => PaymentMethod::Cash->value,
            'status' => TransactionStatus::Paid->value,
        ]);
    }

    // --- Pengaturan ---

    public function testSettingsShowsOwnDepositsAndClustersReadOnly(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $mine = OfficerDeposit::factory()->create(['officer_id' => $officer->id]);
        $others = OfficerDeposit::factory()->create();
        $this->actingAs($officer);

        Livewire::test(FieldSettings::class)
            ->assertSee($cluster->name)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$others]);
    }

    public function testDashboardShowsOfficerCommissionForThisMonth(): void
    {
        $officer = User::factory()->fieldOfficer()->create(['commission_per_customer' => 4000]);
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);
        $customer = Customer::factory()->create([
            'cluster_id' => $cluster->id,
            'billing_amount' => 100_000,
        ]);
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'period' => now()->startOfMonth(),
            'billed_amount' => 100_000,
            'paid_amount' => 100_000,
        ]);

        $this->actingAs($officer);

        $this->assertSame(
            4_000.0,
            Livewire::test(FieldDashboard::class)->instance()->stats()['commission'],
        );

        $this->get('/petugas')->assertOk()->assertSee('Rp 4.000');
    }

    /**
     * Petugas kini boleh mencatat TRANSFER. Aturan uangnya tidak berubah:
     * officer_id tetap NULL (uang tidak lewat petugas), sehingga tertagih tidak
     * naik dan Harus Ditarik justru turun — tapi komisi tetap didapat karena
     * dihitung per daerah, bukan per officer_id.
     */
    public function testOfficerTransferKeepsMoneyRulesButStillEarnsCommission(): void
    {
        $officer = User::factory()->fieldOfficer()->create(['commission_per_customer' => 4000]);
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);
        $customer = Customer::factory()->create([
            'cluster_id' => $cluster->id,
            'billing_amount' => 100_000,
        ]);

        $this->actingAs($officer);

        Livewire::withQueryParams([
            'customer_id' => $customer->getKey(),
            'period' => now()->format('Y-m'),
        ])
            ->test(CreateFieldTransaction::class)
            ->fillForm(['payment_method' => PaymentMethod::Transfer->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $transaction = Transaction::where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame(PaymentMethod::Transfer, $transaction->payment_method);
        $this->assertNull($transaction->officer_id, 'Transfer tidak boleh terikat petugas.');
        $this->assertSame(TransactionStatus::Paid, $transaction->status);

        $progress = app(BillingService::class)->officerProgress($officer->id, now());
        $this->assertSame(0.0, $progress['collected'], 'Transfer bukan uang di tangan petugas.');
        $this->assertSame(0.0, $progress['target'], 'Tagihan yang ditransfer tidak perlu ditarik lagi.');

        $this->assertSame(4_000.0, app(BillingService::class)
            ->commissionQuery(now()->startOfMonth())
            ->find($officer->getKey())
            ->commission_amount);
    }

    public function testTransactionsPageMarksTransferPaidCustomerAsLunas(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $customer = Customer::factory()->create(['cluster_id' => $cluster->id]);
        Transaction::factory()->transfer()->create([
            'customer_id' => $customer->id,
            'period' => now()->startOfMonth(),
        ]);

        $this->actingAs($officer);

        Livewire::test(FieldTransactions::class)
            ->set('filters.payment_status', 'paid')
            ->assertCanSeeTableRecords([$customer]);
    }

    public function testTransactionsPageShowsCustomerAddress(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        Customer::factory()->create([
            'cluster_id' => $cluster->id,
            'address' => 'Dusun Krajan RT 03',
        ]);

        $this->actingAs($officer);

        Livewire::test(FieldTransactions::class)
            ->assertTableColumnExists('address')
            ->assertSee('Dusun Krajan RT 03');
    }

    public function testRecordPaymentFormShowsCustomerAddress(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $customer = Customer::factory()->create([
            'cluster_id' => $cluster->id,
            'address' => 'Dusun Sumberagung RT 01',
        ]);

        $this->actingAs($officer);

        Livewire::withQueryParams(['customer_id' => $customer->getKey()])
            ->test(CreateFieldTransaction::class)
            ->assertSee('Dusun Sumberagung RT 01');
    }

    /** Petugas dengan >1 daerah bisa menyaring daftar pelanggannya per daerah. */
    public function testCustomerListCanBeFilteredByDaerah(): void
    {
        [$officer, $utara] = $this->officerWithCluster();
        $selatan = Cluster::factory()->create(['officer_id' => $officer->id]);

        $diUtara = Customer::factory()->create(['cluster_id' => $utara->id]);
        $diSelatan = Customer::factory()->create(['cluster_id' => $selatan->id]);

        $this->actingAs($officer);

        Livewire::test(ListFieldCustomers::class)
            ->assertCanSeeTableRecords([$diUtara, $diSelatan])
            ->filterTable('cluster_id', $selatan->id)
            ->assertCanSeeTableRecords([$diSelatan])
            ->assertCanNotSeeTableRecords([$diUtara]);
    }

    public function testTransactionsPageCanBeFilteredByDaerah(): void
    {
        [$officer, $utara] = $this->officerWithCluster();
        $selatan = Cluster::factory()->create(['officer_id' => $officer->id]);

        $diUtara = Customer::factory()->create(['cluster_id' => $utara->id]);
        $diSelatan = Customer::factory()->create(['cluster_id' => $selatan->id]);

        $this->actingAs($officer);

        Livewire::test(FieldTransactions::class)
            ->assertCanSeeTableRecords([$diUtara, $diSelatan])
            ->set('filters.cluster_id', $selatan->id)
            ->assertCanSeeTableRecords([$diSelatan])
            ->assertCanNotSeeTableRecords([$diUtara]);
    }

    /** Filter Daerah selalu tampil, termasuk saat petugas hanya pegang satu daerah. */
    public function testDaerahFilterIsVisibleEvenWithOneDaerah(): void
    {
        [$officer] = $this->officerWithCluster();
        $this->actingAs($officer);

        $this->assertCount(1, FieldCustomerResource::daerahOptions());

        Livewire::test(ListFieldCustomers::class)
            ->assertTableFilterVisible('cluster_id');

        Livewire::test(FieldTransactions::class)
            ->assertFormFieldVisible('cluster_id', 'filtersForm');
    }

    /**
     * Tambah & edit pelanggan dicabut dari petugas (kembali ke PRD §6).
     * Tombolnya hilang DAN route-nya tidak ada — bukan sekadar disembunyikan.
     */
    public function testOfficerCannotCreateOrEditCustomer(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $customer = Customer::factory()->create(['cluster_id' => $cluster->id]);

        $this->actingAs($officer);

        $this->assertFalse($officer->can('create', Customer::class));
        $this->assertFalse($officer->can('update', $customer));

        Livewire::test(ListFieldCustomers::class)
            ->assertActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('edit', record: $customer);

        $this->assertFalse(
            array_key_exists('create', FieldCustomerResource::getPages()),
            'Route create pelanggan harus ikut hilang, bukan cuma tombolnya.',
        );
        $this->assertFalse(array_key_exists('edit', FieldCustomerResource::getPages()));
    }
}
