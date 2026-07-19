<?php

namespace App\Imports;

use App\Enums\CustomerStatus;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Package;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Import massal pelanggan dari Excel klien (TASK-11).
 *
 * Cleaning rules dari spec: WA scientific notation → string 62xxx,
 * kolom Paket berupa angka (110 → default_price 110000), status text
 * bebas → enum (fallback active), URL maps divalidasi.
 * Cluster & harga per-baris di Excel; cluster opsional (assign via UI nanti).
 */
class CustomerImport
{
    public int $imported = 0;

    /** @var array<int, string> Pesan gagal per baris (untuk log downloadable). */
    public array $failures = [];

    /**
     * Template xlsx untuk diisi user: heading + baris contoh
     * (format sama persis dengan yang dibaca import()).
     */
    public static function templateContent(): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['NAMA', 'WA', 'PAKET', 'HARGA', 'CLUSTER', 'ALAMAT', 'TGL TAGIH', 'KET.', 'MAPS'],
            ['Budi', '081234567890', 110, 110000, 'PADI', 'PADI 1', 5, 'AKTIF', 'https://maps.app.goo.gl/contoh'],
            ['Sari', '6281234567891', 100, '', 'KAPAS', 'KAPAS 2', 20, 'ISOLIR', ''],
        ], null, 'A1');

        $tmp = tempnam(sys_get_temp_dir(), 'tpl');
        (new Xlsx($spreadsheet))->save($tmp);
        $content = file_get_contents($tmp);
        unlink($tmp);

        return $content;
    }

    /**
     * Baca file xlsx: baris pertama = heading, sisanya data.
     */
    public function import(string $path): void
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();

        // Heading dinormalisasi: lowercase, trim, buang titik ("KET." → "ket").
        $headings = array_map(
            fn ($h) => rtrim(strtolower(trim((string) $h)), '.'),
            array_shift($rows) ?? [],
        );

        foreach ($rows as $i => $values) {
            $row = array_combine($headings, array_pad(array_slice($values, 0, count($headings)), count($headings), null));
            $line = $i + 2; // nomor baris di Excel (1-based + heading)

            if (blank($row['nama'] ?? null)) {
                continue; // baris kosong dilewati tanpa dihitung gagal
            }

            try {
                $packageId = $this->lookupPackage($row['paket'] ?? null);

                Customer::create([
                    'name' => trim((string) $row['nama']),
                    'whatsapp_number' => $this->cleanWhatsappNumber($row['wa'] ?? null),
                    'cluster_id' => $this->lookupCluster($row['cluster'] ?? null),
                    'package_id' => $packageId,
                    // Prioritas kolom HARGA; kosong → fallback harga paket.
                    'billing_amount' => $this->cleanAmount($row['harga'] ?? null)
                        ?? ($packageId ? Package::find($packageId)->default_price : null),
                    'address' => $row['alamat'] ?? null,
                    'maps_url' => $this->validUrl($row['maps'] ?? null),
                    'status' => $this->cleanStatus($row['ket'] ?? null),
                    'billing_day' => $this->cleanBillingDay($row['tgl tagih'] ?? null),
                    'registered_at' => now()->toDateString(),
                ]);

                $this->imported++;
            } catch (Throwable $e) {
                $this->failures[$line] = $e->getMessage();
                Log::warning("Import pelanggan gagal (baris {$line}): {$e->getMessage()}");
            }
        }
    }

    /** Scientific notation float → string digit, normalisasi ke 62xxx. */
    public function cleanWhatsappNumber(mixed $v): ?string
    {
        if (blank($v)) {
            return null;
        }

        $s = is_float($v) ? sprintf('%.0f', $v) : (string) $v;
        $s = preg_replace('/\D/', '', $s);

        if ($s === '') {
            return null;
        }

        return str_starts_with($s, '62') ? $s
            : (str_starts_with($s, '0') ? '62'.substr($s, 1) : $s);
    }

    /** Angka paket (110) → id package dengan default_price 110000. */
    public function lookupPackage(mixed $v): ?string
    {
        if (blank($v) || ! is_numeric($v)) {
            return null;
        }

        $id = Package::where('default_price', ((int) $v) * 1000)->value('id');

        if ($id === null) {
            Log::warning("Import pelanggan: paket '{$v}' tidak ditemukan di master paket.");
        }

        return $id;
    }

    /** Nama cluster → id (case-insensitive); kosong/tak dikenal → null + log. */
    public function lookupCluster(mixed $v): ?string
    {
        if (blank($v)) {
            return null;
        }

        $id = Cluster::whereRaw('lower(name) = ?', [strtolower(trim((string) $v))])->value('id');

        if ($id === null) {
            Log::warning("Import pelanggan: cluster '{$v}' tidak ditemukan, dikosongkan (assign via UI).");
        }

        return $id;
    }

    /** Nominal tagihan; angka gaya lama (110 = 110rb) dinormalisasi ×1000. */
    public function cleanAmount(mixed $v): ?float
    {
        if (blank($v) || ! is_numeric($v)) {
            return null;
        }

        $n = (float) $v;

        // ponytail: heuristik <1000 dianggap format ribuan gaya Excel lama
        return $n < 1000 ? $n * 1000 : $n;
    }

    /** Tanggal tagih 1-31; invalid → 1 (default). */
    public function cleanBillingDay(mixed $v): int
    {
        if (is_numeric($v) && (int) $v >= 1 && (int) $v <= 31) {
            return (int) $v;
        }

        return 1;
    }

    /** Text status bebas (AKTIF/ISOLIR/OFF) → enum; invalid → active + log. */
    public function cleanStatus(mixed $v): CustomerStatus
    {
        $s = strtolower(trim((string) $v));

        $status = match ($s) {
            'aktif', 'active' => CustomerStatus::Active,
            'isolir', 'suspended' => CustomerStatus::Suspended,
            'off', 'terminated' => CustomerStatus::Terminated,
            default => null,
        };

        if ($status === null) {
            Log::warning("Import pelanggan: status '{$v}' tidak valid, fallback ke active.");

            return CustomerStatus::Active;
        }

        return $status;
    }

    /** URL maps; invalid → null + log. */
    public function validUrl(mixed $v): ?string
    {
        if (blank($v)) {
            return null;
        }

        if (filter_var($v, FILTER_VALIDATE_URL) === false) {
            Log::warning("Import pelanggan: URL maps '{$v}' tidak valid, dikosongkan.");

            return null;
        }

        return (string) $v;
    }
}
