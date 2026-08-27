<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Enums\PaymentMethod;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DemoSeeder menyiapkan data untuk screenshot user guide: setiap halaman yang
 * didokumentasikan harus punya isi, bukan state kosong.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
    }

    public function testFillsSeveralPeriodsWithPaidAndUnpaidCustomers(): void
    {
        $periods = Transaction::query()
            ->withoutGlobalScopes()
            ->distinct()
            ->pluck('period');

        $this->assertGreaterThanOrEqual(3, $periods->count(), 'Butuh >=3 periode untuk chart tren & filter periode.');

        // Bulan berjalan harus punya sisi lunas DAN belum bayar supaya pie
        // Tagihan Bulanan dan filter Status Bayar tidak berat sebelah.
        $paid = Transaction::withoutGlobalScopes()->forPeriod(now())->count();
        $billable = Customer::withoutGlobalScopes()->billable()->count();

        $this->assertGreaterThan(0, $paid);
        $this->assertLessThan($billable, $paid);
    }

    public function testHasBothCashAndTransferPayments(): void
    {
        $this->assertGreaterThan(
            0,
            Transaction::withoutGlobalScopes()->where('payment_method', PaymentMethod::Cash)->count(),
        );
        $this->assertGreaterThan(
            0,
            Transaction::withoutGlobalScopes()->where('payment_method', PaymentMethod::Transfer)->count(),
        );
    }

    public function testEveryOfficerHasDepositsWithRemainingBalance(): void
    {
        $officers = User::where('role', 'field_officer')->get();
        $this->assertNotEmpty($officers);

        foreach ($officers as $officer) {
            $this->assertGreaterThan(
                0,
                OfficerDeposit::where('officer_id', $officer->id)->count(),
                "Petugas {$officer->name} tanpa setoran — panel setoran akan kosong.",
            );
        }

        // Minimal satu petugas masih menyimpan uang (kolom "Sisa" merah).
        $remaining = $officers->map(
            fn (User $officer): float => app(BillingService::class)->officerProgress($officer->id, now())['remaining'],
        );

        $this->assertTrue($remaining->contains(fn (float $value): bool => $value > 0));
    }

    public function testCommissionPageHasNonZeroTotal(): void
    {
        $this->assertGreaterThan(
            0,
            app(BillingService::class)->commissionTotal(now()->startOfMonth()),
            'Tanpa pelanggan lunas di cluster petugas, halaman Komisi & Laporan Komisi bernilai 0.',
        );
    }

    public function testHasCustomersWithPhotoAndMapsAndVariedStatuses(): void
    {
        $withPhoto = Customer::withoutGlobalScopes()->whereNotNull('house_photo_url')->first();
        $this->assertNotNull($withPhoto, 'Tombol Foto Rumah tidak bisa di-screenshot dalam keadaan aktif.');
        $this->assertFileExists(storage_path('app/public/'.$withPhoto->house_photo_url));

        $this->assertGreaterThan(0, Customer::withoutGlobalScopes()->whereNotNull('maps_url')->count());

        foreach ([CustomerStatus::Active, CustomerStatus::Suspended, CustomerStatus::Terminated] as $status) {
            $this->assertGreaterThan(
                0,
                Customer::withoutGlobalScopes()->where('status', $status)->count(),
                "Status {$status->value} tidak terwakili.",
            );
        }
    }

    public function testEveryOfficerHasUnpaidCustomersDueToday(): void
    {
        // Dashboard petugas hanya menampilkan yang jatuh tempo hari ini DAN belum
        // lunas bulan ini; tanpa ini contoh utama panduan petugas tampil kosong.
        foreach (User::where('role', 'field_officer')->get() as $officer) {
            $count = Customer::withoutGlobalScopes()
                ->dueToday()
                ->whereHas('cluster', fn ($query) => $query->where('officer_id', $officer->id))
                ->whereDoesntHave('transactions', fn ($query) => $query
                    ->forPeriod(now())
                    ->where('status', 'paid'))
                ->count();

            $this->assertGreaterThan(0, $count, "Petugas {$officer->name} tidak punya tagihan hari ini.");
        }
    }

    public function testWritesAuditTrail(): void
    {
        // AuditService melewatkan perubahan tanpa aktor; seeder harus login dulu.
        $this->assertGreaterThan(0, AuditLog::count(), 'Halaman Log Aktivitas akan kosong.');
    }

    public function testIsNotPartOfTheDefaultSeeder(): void
    {
        // Demo data tidak boleh ikut `php artisan db:seed` di produksi.
        $this->assertStringNotContainsString(
            'DemoSeeder',
            file_get_contents(database_path('seeders/DatabaseSeeder.php')),
        );
    }
}
