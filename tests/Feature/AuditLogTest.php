<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Enums\CustomerStatus;
use App\Filament\Pages\MonthlyBilling;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\AuditLog;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    // --- Kontrol akses ---

    public function testSuperAdminCanAccessAuditLogResource(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(AuditLogResource::getUrl('index'))->assertOk();
    }

    public function testAdminCannotAccessAuditLogResource(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(AuditLogResource::getUrl('index'))->assertForbidden();
    }

    public function testFieldOfficerCannotAccessAuditLogResource(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(AuditLogResource::getUrl('index'))->assertForbidden();
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $this->get(AuditLogResource::getUrl('index'))->assertRedirect();
    }

    // --- Perekaman perubahan data ---

    public function testCustomerCreationIsLogged(): void
    {
        $this->actingAs($admin = User::factory()->admin()->create());

        $customer = Customer::factory()->create(['name' => 'Pelanggan Audit']);

        $log = $this->latestLogFor(Customer::class, AuditEvent::Created);

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($customer->getKey(), $log->subject_id);
        $this->assertSame('Pelanggan Audit', $log->subject_label);
        $this->assertSame([null, 'Pelanggan Audit'], $log->changed_values['name']);
    }

    public function testStatusChangeIsLoggedWithOldAndNewValue(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
        $this->actingAs(User::factory()->admin()->create());

        $customer->update(['status' => CustomerStatus::Suspended]);

        $log = $this->latestLogFor(Customer::class, AuditEvent::Updated);

        $this->assertSame(['active', 'suspended'], $log->changed_values['status']);
    }

    public function testQuickActionStatusChangeIsLogged(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
        $this->actingAs(User::factory()->admin()->create());

        // Quick action ISOLIR lewat tabel — jalur tulis berbeda, log harus sama.
        Livewire::test(ListCustomers::class)->callTableAction('suspend', $customer);

        $this->assertSame(
            ['active', 'suspended'],
            $this->latestLogFor(Customer::class, AuditEvent::Updated)->changed_values['status'],
        );
    }

    public function testPaymentEntryIsLogged(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Transaction::factory()->create(['paid_amount' => 150000]);

        $log = $this->latestLogFor(Transaction::class, AuditEvent::Created);

        $this->assertSame(
            '150000',
            (string) (int) $log->changed_values['paid_amount'][1],
        );
    }

    public function testDeletionIsLogged(): void
    {
        $customer = Customer::factory()->create(['name' => 'Akan Dihapus']);
        $this->actingAs(User::factory()->admin()->create());

        $customer->forceDelete();

        $log = $this->latestLogFor(Customer::class, AuditEvent::Deleted);

        $this->assertSame('Akan Dihapus', $log->subject_label);
    }

    public function testPasswordIsNeverStored(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs(User::factory()->superAdmin()->create());

        $user->update(['password' => 'rahasia-baru', 'name' => 'Nama Baru']);

        $log = $this->latestLogFor(User::class, AuditEvent::Updated);

        $this->assertArrayNotHasKey('password', $log->changed_values);
        $this->assertArrayNotHasKey('remember_token', $log->changed_values);
        $this->assertArrayHasKey('name', $log->changed_values);
    }

    public function testTimestampOnlySavesAreNotLogged(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $customer->touch();

        $this->assertSame(0, AuditLog::where('event', AuditEvent::Updated)->count());
    }

    public function testAuditLogIsNotSelfAudited(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Customer::factory()->create();

        // Kalau AuditLog ikut teraudit, satu tulisan akan berlipat tanpa batas.
        $this->assertSame(0, AuditLog::where('subject_type', AuditLog::class)->count());
    }

    public function testUnauthenticatedWritesAreNotLogged(): void
    {
        // Seeder & artisan jalan tanpa user — tidak ada aktor untuk dicatat.
        Customer::factory()->create();

        $this->assertSame(0, AuditLog::count());
    }

    // --- Akses menu, login, export ---

    public function testMenuAccessIsLogged(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(CustomerResource::getUrl('index'))->assertOk();

        $log = AuditLog::where('event', AuditEvent::Accessed)->firstOrFail();

        $this->assertSame('admin/customers', $log->url);
        $this->assertSame(__('Customers'), $log->subject_label);
    }

    public function testPlainPageAccessUsesNavigationLabel(): void
    {
        // Cabang label yang berbeda dari resource: Page punya getNavigationLabel().
        $this->actingAs(User::factory()->admin()->create());

        $this->get(MonthlyBilling::getUrl())->assertOk();

        $this->assertSame(
            MonthlyBilling::getNavigationLabel(),
            AuditLog::where('event', AuditEvent::Accessed)->value('subject_label'),
        );
    }

    public function testRepeatedMenuAccessIsDeduped(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(CustomerResource::getUrl('index'));
        $this->get(CustomerResource::getUrl('index'));

        $this->assertSame(1, AuditLog::where('event', AuditEvent::Accessed)->count());
    }

    public function testForbiddenPageIsNotLoggedAsAccess(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(AuditLogResource::getUrl('index'))->assertForbidden();

        $this->assertSame(0, AuditLog::where('event', AuditEvent::Accessed)->count());
    }

    public function testLoginAndLogoutAreLogged(): void
    {
        $user = User::factory()->admin()->create();

        Auth::login($user);
        Auth::logout();

        $this->assertSame($user->id, AuditLog::where('event', AuditEvent::LoggedIn)->value('user_id'));
        $this->assertSame($user->id, AuditLog::where('event', AuditEvent::LoggedOut)->value('user_id'));
    }

    public function testExportIsLogged(): void
    {
        Customer::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListCustomers::class)
            ->callAction('export')
            ->assertFileDownloaded();

        $this->assertSame(
            'pelanggan',
            AuditLog::where('event', AuditEvent::Exported)->value('subject_label'),
        );
    }

    // --- Tampilan & retensi ---

    public function testChangesSummaryRendersOldToNewValues(): void
    {
        $log = new AuditLog(['changed_values' => [
            'status' => ['active', 'suspended'],
            'name' => [null, 'Budi'],
        ]]);

        // Nama field ikut terjemahan aktif (Name → Nama saat locale id).
        $this->assertSame(
            sprintf('%s: active → suspended · %s: Budi', __('Status'), __('Name')),
            $log->changesSummary(),
        );
    }

    public function testChangesColumnRendersSummaryOnceNotPerField(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
        $this->actingAs(User::factory()->superAdmin()->create());

        // Dua field berubah sekaligus (status + suspended_at dari Customer::booted()).
        $customer->update(['status' => CustomerStatus::Suspended]);
        $log = $this->latestLogFor(Customer::class, AuditEvent::Updated);

        $this->assertCount(2, $log->changed_values);

        // Kolom JSON: tanpa state() Filament memformat per elemen → ringkasan ganda.
        Livewire::test(ListAuditLogs::class)
            ->assertTableColumnStateSet('changed_values', $log->changesSummary(), $log);
    }

    public function testPruneRemovesEntriesOlderThanRetention(): void
    {
        // created_at bukan fillable — harus di-set eksplisit.
        AuditLog::create(['event' => AuditEvent::Accessed])
            ->forceFill(['created_at' => now()->subMonths(7)])->save();
        AuditLog::create(['event' => AuditEvent::Accessed]);

        $this->artisan('model:prune', ['--model' => [AuditLog::class]]);

        $this->assertSame(1, AuditLog::count());
    }

    public function testSuperAdminCanExportAuditLog(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Cluster::factory()->create();

        Livewire::test(AuditLogResource::getPages()['index']->getPage())
            ->callAction('export')
            ->assertFileDownloaded();
    }

    private function latestLogFor(string $subjectType, AuditEvent $event): AuditLog
    {
        // Urut ULID, bukan created_at: timestamp-nya beresolusi detik jadi bisa seri.
        return AuditLog::where('subject_type', $subjectType)
            ->where('event', $event)
            ->latest('id')
            ->firstOrFail();
    }
}
