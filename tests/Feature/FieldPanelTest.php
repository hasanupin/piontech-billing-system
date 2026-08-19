<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Filament\Auth\Login;
use App\Filament\Field\Pages\Dashboard as FieldDashboard;
use App\Filament\Field\Pages\Settings as FieldSettings;
use App\Filament\Field\Pages\Transactions as FieldTransactions;
use App\Filament\Field\Resources\Customers\FieldCustomerResource;
use App\Filament\Field\Resources\Customers\Pages\CreateFieldCustomer;
use App\Filament\Field\Resources\Customers\Pages\ListFieldCustomers;
use App\Filament\Field\Resources\Transactions\FieldTransactionResource;
use App\Filament\Field\Resources\Transactions\Pages\CreateFieldTransaction;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
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

    public function testOfficerCanCreateCustomerInOwnCluster(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $this->actingAs($officer);

        Livewire::test(CreateFieldCustomer::class)
            ->fillForm([
                'name' => 'Pelanggan Baru',
                'cluster_id' => $cluster->id,
                'billing_amount' => '150.000,00',
                'billing_day' => 18,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'name' => 'Pelanggan Baru',
            'cluster_id' => $cluster->id,
        ]);
    }

    public function testOfficerCannotOpenEditForCustomerOutsideOwnCluster(): void
    {
        [$officer] = $this->officerWithCluster();
        $outsider = Customer::factory()->create();
        $this->actingAs($officer);

        // Global scope cluster ikut di route-model binding → 404 sebelum policy.
        $this->get(FieldCustomerResource::getUrl('edit', ['record' => $outsider]))
            ->assertNotFound();
    }

    public function testReferralFieldHiddenFromOfficerAndNotPersisted(): void
    {
        [$officer, $cluster] = $this->officerWithCluster();
        $this->actingAs($officer);

        Livewire::test(CreateFieldCustomer::class)
            ->assertFormFieldHidden('referral_id');
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
}
