# Panduan Pengguna — cara membuat ulang

Empat halaman HTML mandiri (`index.html`, `ceo.html`, `admin.html`, `petugas.html`)
memakai `assets/guide.css` dan gambar di `screenshots/`. Buka langsung di browser,
atau `Ctrl+P` untuk menyimpannya sebagai PDF.

## Menyiapkan data contoh

Screenshot diambil dari data peragaan, bukan data asli:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder   # WAJIB — tanpa ini banyak halaman kosong
php artisan storage:link                 # supaya foto rumah tampil
```

`DemoSeeder` sengaja **tidak** ikut `php artisan db:seed` supaya tidak pernah masuk
ke produksi. Isinya transaksi 6 periode (tunai & transfer), setoran dengan sisa,
penerima komisi + pelanggan referal, foto rumah & titik lokasi, ketiga status
pelanggan, tagihan jatuh tempo hari ini untuk tiap petugas, serta jejak Log Aktivitas.
`tests/Feature/DemoSeederTest.php` menjaga semua itu tetap terpenuhi.

## Mengambil ulang screenshot

Jalankan `php artisan serve`, lalu ambil dengan Playwright:

| Kelompok | Akun | Ukuran layar |
|---|---|---|
| Panel kantor (CEO) | `admin@example.com` | 1440 × 900 |
| Panel kantor (admin) | `billing.admin@example.com` | 1440 × 900 |
| Panel petugas | `budi@example.com` | 414 × 860 |

Kata sandi semua akun contoh: `password`.

Catatan saat mengambil:

- Login lewat halaman `/admin/login` **secara langsung**; membuka `/petugas` lebih
  dulu membuat halaman melewati dua kali redirect dan form kerap gagal ter-submit.
- Login petugas dilakukan di ukuran desktop, baru viewport diubah ke ukuran HP.
- Halaman login membatasi **5 percobaan per menit**. Bila mulai gagal, jalankan
  `php artisan cache:clear` dan tunggu sebentar.
- Bila form berhenti merespons setelah banyak navigasi, buat konteks browser baru.
- Tombol Foto Rumah pada baris pertama bisa saja nonaktif — pilih baris yang aktif
  (`button[title="Foto Rumah"]:not([disabled])`).

## Kalau tampilan aplikasi berubah

Ambil ulang gambar yang terdampak dengan nama berkas yang sama, lalu periksa apakah
teks langkah pada halaman terkait masih cocok. Nama menu di panduan mengikuti
`lang/id.json`.
