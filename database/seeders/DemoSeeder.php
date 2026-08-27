<?php

namespace Database\Seeders;

use App\Enums\CustomerStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Data peragaan untuk screenshot user guide (docs/user-guide/).
 *
 * SENGAJA tidak dipanggil DatabaseSeeder — jalankan eksplisit:
 *   php artisan db:seed --class=DemoSeeder
 * supaya tidak pernah ikut masuk ke produksi.
 *
 * Dijalankan SETELAH seeder utama; tugasnya mengisi halaman yang pada seed
 * standar tampil kosong (Dashboard, Tagihan Bulanan, Komisi, 6 laporan,
 * Setoran Petugas, Log Aktivitas).
 */
class DemoSeeder extends Seeder
{
    /** Berapa bulan ke belakang yang diisi transaksi. */
    private const MONTHS = 6;

    public function run(): void
    {
        $admin = User::where('role', Role::Admin)->firstOrFail();
        $officers = User::where('role', Role::FieldOfficer)->get();

        // AuditService melewatkan perubahan tanpa aktor. Login dulu supaya
        // seluruh isi seeder ini juga menghasilkan jejak di Log Aktivitas.
        Auth::login($admin);

        $this->varyOfficerCommissionRates($officers);
        $this->varyCustomerStatuses();
        $this->attachPhotosAndMaps();
        $this->createTransactions($officers, $admin);
        $this->createDeposits($officers, $admin);
        $this->scheduleTodaysCollections($officers);

        Auth::logout();
    }

    /**
     * Seed standar hanya punya AKTIF + ISOLIR; tambah BERHENTI supaya badge
     * status dan Laporan Pelanggan punya ketiga nilainya.
     */
    private function varyCustomerStatuses(): void
    {
        Customer::withoutGlobalScopes()
            ->where('status', CustomerStatus::Active)
            ->latest('id')
            ->take(3)
            ->get()
            ->each(fn (Customer $customer) => $customer->update([
                'status' => CustomerStatus::Terminated,
            ]));
    }

    /**
     * Tombol "Foto Rumah" dan "Cek Lokasi" mati kalau datanya kosong — isi
     * sebagian pelanggan supaya keduanya bisa di-screenshot dalam keadaan aktif.
     */
    private function attachPhotosAndMaps(): void
    {
        $path = 'foto-rumah/contoh-rumah.jpg';

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, $this->placeholderPhoto());
        }

        Customer::withoutGlobalScopes()
            ->billable()
            ->take(12)
            ->get()
            ->each(function (Customer $customer, int $index) use ($path): void {
                $customer->update([
                    'house_photo_url' => $path,
                    // Koordinat sekitar Karanganyar, Jawa Tengah.
                    'maps_url' => sprintf(
                        'https://maps.google.com/?q=%.6f,%.6f',
                        -7.5561 + ($index * 0.0012),
                        110.8317 + ($index * 0.0009),
                    ),
                ]);
            });
    }

    /**
     * JPEG polos bergradien — cukup untuk memperagakan modal foto rumah tanpa
     * menitipkan berkas biner di repo.
     */
    private function placeholderPhoto(): string
    {
        $image = imagecreatetruecolor(640, 480);

        for ($y = 0; $y < 480; $y++) {
            $shade = (int) (40 + ($y / 480) * 90);
            imagefilledrectangle($image, 0, $y, 640, $y, imagecolorallocate($image, $shade, $shade + 20, $shade + 40));
        }

        imagestring($image, 5, 210, 230, 'Contoh Foto Rumah', imagecolorallocate($image, 255, 255, 255));

        ob_start();
        imagejpeg($image, null, 85);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * Tarif komisi berbeda per petugas supaya halaman Komisi tidak seragam.
     * Petugas baru tetap memakai default kolom (Rp 4.000).
     */
    private function varyOfficerCommissionRates($officers): void
    {
        $rates = [4000, 5000, 4500];

        $officers->each(fn (User $officer, int $index) => $officer->update([
            'commission_per_customer' => $rates[$index % count($rates)],
        ]));
    }

    /**
     * Enam periode terakhir. Bulan-bulan lampau hampir lunas; bulan berjalan
     * sengaja disisakan supaya filter "Belum Bayar" dan pie tagihan ada isinya.
     */
    private function createTransactions($officers, User $admin): void
    {
        if (Transaction::withoutGlobalScopes()->exists()) {
            return;
        }

        $customers = Customer::withoutGlobalScopes()->billable()->get();

        foreach (range(self::MONTHS - 1, 0) as $monthsAgo) {
            $period = now()->startOfMonth()->subMonths($monthsAgo);
            $isCurrentMonth = $monthsAgo === 0;

            // Bulan lampau 90% terbayar, bulan berjalan baru ~55%.
            $paidCount = (int) round($customers->count() * ($isCurrentMonth ? 0.55 : 0.9));

            foreach ($customers->take($paidCount) as $index => $customer) {
                // Tiap pelanggan ke-4 membayar transfer langsung ke admin —
                // transfer tidak punya officer_id dan tidak masuk setoran petugas.
                $isTransfer = $index % 4 === 3;
                $officer = $officers->firstWhere('id', $customer->cluster?->officer_id);

                Transaction::create([
                    'customer_id' => $customer->getKey(),
                    'officer_id' => $isTransfer ? null : $officer?->getKey(),
                    'period' => $period,
                    'billed_amount' => $customer->billing_amount,
                    'paid_amount' => $customer->billing_amount,
                    'payment_method' => $isTransfer ? PaymentMethod::Transfer : PaymentMethod::Cash,
                    'recorded_by' => $isTransfer ? $admin->getKey() : ($officer?->getKey() ?? $admin->getKey()),
                    'paid_at' => $period->copy()->addDays(min($customer->billing_day, 27))->setTime(19, 30),
                ]);
            }
        }
    }

    /**
     * Dashboard petugas hanya menampilkan pelanggan yang jatuh tempo HARI INI
     * dan belum lunas bulan ini. Tanpa penjadwalan ini daftarnya kosong, jadi
     * tiap petugas diberi beberapa tagihan yang jatuh tempo hari ini.
     */
    private function scheduleTodaysCollections($officers): void
    {
        foreach ($officers as $officer) {
            Customer::withoutGlobalScopes()
                ->billable()
                ->whereHas('cluster', fn ($query) => $query->where('officer_id', $officer->getKey()))
                ->whereDoesntHave('transactions', fn ($query) => $query
                    ->forPeriod(now())
                    ->where('status', TransactionStatus::Paid))
                ->take(3)
                ->get()
                ->each(fn (Customer $customer) => $customer->update([
                    'billing_day' => now()->day,
                ]));
        }
    }

    /**
     * Setoran per petugas per periode, sengaja kurang dari yang tertagih supaya
     * kolom "Sisa" di panel setoran menunjukkan angka merah.
     */
    private function createDeposits($officers, User $admin): void
    {
        if (OfficerDeposit::exists()) {
            return;
        }

        foreach ($officers as $officer) {
            foreach (range(self::MONTHS - 1, 0) as $monthsAgo) {
                $period = now()->startOfMonth()->subMonths($monthsAgo);

                $collected = (float) Transaction::withoutGlobalScopes()
                    ->where('officer_id', $officer->getKey())
                    ->forPeriod($period)
                    ->sum('paid_amount');

                if ($collected <= 0) {
                    continue;
                }

                // Bulan lampau disetor penuh; bulan berjalan baru sebagian.
                $amount = $monthsAgo === 0 ? round($collected * 0.6, -3) : $collected;

                OfficerDeposit::create([
                    'officer_id' => $officer->getKey(),
                    'period' => $period,
                    'amount' => $amount,
                    'received_by' => $admin->getKey(),
                    'deposited_at' => $period->copy()->endOfMonth()->min(now())->setTime(16, 0),
                ]);
            }
        }
    }
}
