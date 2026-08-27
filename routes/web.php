<?php

use App\Enums\Role;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(
    auth()->user()?->isRole(Role::FieldOfficer) ? '/petugas' : '/admin',
));

// Satu halaman login untuk semua role; namanya 'login' sekaligus jadi tujuan
// fallback Laravel untuk panel /petugas yang tidak punya halaman login sendiri.
Route::redirect('/login', '/admin/login')->name('login');
Route::redirect('/petugas/login', '/admin/login');

/*
 * Panduan pengguna (docs/user-guide/) disajikan lewat aplikasi, bukan dari
 * public/, supaya hanya bisa dibuka setelah login. Halamannya menyesuaikan
 * peran, jadi menu di panel cukup menunjuk ke satu URL: route('guide').
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/panduan', function () {
        $user = auth()->user();

        return redirect()->route('guide.file', [
            'file' => match (true) {
                $user->isRole(Role::FieldOfficer) => 'petugas.html',
                $user->isSuperAdmin() => 'ceo.html',
                default => 'admin.html',
            },
        ]);
    })->name('guide');

    Route::get('/panduan/{file}', function (string $file) {
        // Tipe MIME ditentukan dari ekstensi, bukan ditebak dari isi berkas:
        // tebakan finfo mengirim CSS sebagai text/plain dan browser menolaknya,
        // sehingga panduan tampil tanpa gaya sama sekali. Daftar ini sekaligus
        // menahan berkas non-dokumen seperti README.md yang memuat kredensial
        // akun contoh.
        $types = [
            'html' => 'text/html; charset=UTF-8',
            'css' => 'text/css',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
        ];

        $base = realpath(base_path('docs/user-guide'));
        $path = realpath($base.DIRECTORY_SEPARATOR.$file);
        $extension = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';

        // realpath + cek awalan menutup path traversal (../../.env dsb.).
        abort_unless(
            $base
                && $path
                && str_starts_with($path, $base.DIRECTORY_SEPARATOR)
                && is_file($path)
                && isset($types[$extension]),
            404,
        );

        return response()->file($path, ['Content-Type' => $types[$extension]]);
    })->where('file', '.*')->name('guide.file');
});
