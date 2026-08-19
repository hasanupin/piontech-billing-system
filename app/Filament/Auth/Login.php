<?php

namespace App\Filament\Auth;

use App\Enums\Role;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Panel;

/**
 * Halaman login tunggal untuk semua role.
 *
 * Filament mengevaluasi canAccessPanel() terhadap panel tempat form login
 * dirender, dan LoginResponse mengarah ke panel yang sama. Karena petugas
 * ditolak di panel admin (dan sebaliknya), form ini menjalankan proses login
 * bawaan dalam konteks panel TUJUAN user — sehingga satu halaman melayani
 * keduanya dan langsung mendarat di panel yang benar.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $current = Filament::getCurrentOrDefaultPanel();
        $target = Filament::getPanel($this->panelIdFor($this->form->getState()['email'] ?? null));

        Filament::setCurrentPanel($target);

        try {
            $response = parent::authenticate();
            // URL tujuan dihitung SEKARANG, selagi panel tujuan masih aktif:
            // LoginResponse bawaan baru memanggil Filament::getUrl() saat
            // toResponse(), ketika panel sudah dikembalikan ke panel form ini.
            $url = Filament::getUrl();
        } finally {
            // Form ini hidup di panel admin; kembalikan supaya render ulang
            // (mis. saat kredensial salah) tetap memakai panel aslinya.
            Filament::setCurrentPanel($current);
        }

        return $response ? $this->redirectResponse($url, $target) : null;
    }

    /**
     * Panel tujuan berdasarkan email yang diisi. Ini hanya menentukan panel
     * mana yang dipakai untuk pengecekan akses & redirect — kredensial tetap
     * divalidasi parent, jadi email yang tidak dikenal tidak membocorkan apa pun.
     */
    private function panelIdFor(?string $email): string
    {
        if (blank($email)) {
            return 'admin';
        }

        return User::where('email', $email)->value('role') === Role::FieldOfficer
            ? 'field'
            : 'admin';
    }

    private function redirectResponse(string $url, Panel $target): LoginResponse
    {
        return new class($url, url('/'.$target->getPath())) implements LoginResponse
        {
            public function __construct(private string $url, private string $panelUrl) {}

            public function toResponse($request)
            {
                $intended = session()->pull('url.intended');

                // Deep link hanya dihormati bila memang milik panel tujuan;
                // tautan sisa dari panel lain akan berujung 403 tepat setelah
                // login berhasil — persis kebingungan yang mau dihindari.
                return redirect(
                    filled($intended) && str_starts_with($intended, $this->panelUrl)
                        ? $intended
                        : $this->url,
                );
            }
        };
    }
}
