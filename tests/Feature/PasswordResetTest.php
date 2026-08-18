<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword as ResetPasswordPage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lupa password: halaman bawaan Filament + notifikasi email.
 * Yang dijaga di sini kontraknya, bukan isi emailnya.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function testPanelEnablesPasswordReset(): void
    {
        $this->assertTrue(Filament::getPanel('admin')->hasPasswordReset());
    }

    public function testGuestCanOpenRequestPage(): void
    {
        $this->get(Filament::getPanel('admin')->getRequestPasswordResetUrl())
            ->assertSuccessful();
    }

    public function testLoginPageLinksToPasswordReset(): void
    {
        $this->get(Filament::getPanel('admin')->getLoginUrl())
            ->assertSee(Filament::getPanel('admin')->getRequestPasswordResetUrl());
    }

    public function testRequestSendsResetNotificationToKnownUser(): void
    {
        Notification::fake();

        $user = User::factory()->admin()->create(['email' => 'admin@piontech.test']);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'admin@piontech.test'])
            ->call('request')
            ->assertHasNoFormErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function testRequestSendsNothingForUnknownEmail(): void
    {
        Notification::fake();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'tidak-ada@piontech.test'])
            ->call('request');

        Notification::assertNothingSent();
    }

    public function testTokenFromEmailActuallyChangesThePassword(): void
    {
        $user = User::factory()->admin()->create(['email' => 'admin@piontech.test']);
        $token = Password::broker(Filament::getPanel('admin')->getAuthPasswordBroker())->createToken($user);

        Livewire::test(ResetPasswordPage::class, ['email' => $user->email, 'token' => $token])
            ->fillForm([
                'password' => 'password-baru',
                'passwordConfirmation' => 'password-baru',
            ])
            ->call('resetPassword')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('password-baru', $user->refresh()->password));
    }

    public function testResetLinkEmailIsTranslated(): void
    {
        $this->assertSame('id', config('app.locale'));

        // Body email memakai key JSON dari Laravel — tanpa entri di lang/id.json
        // emailnya tetap berbahasa Inggris meski panel sudah Indonesia.
        $this->assertNotSame('Reset your password', __('Reset your password'));
        $this->assertNotSame(
            'You are receiving this email because we received a password reset request for your account.',
            __('You are receiving this email because we received a password reset request for your account.'),
        );
    }
}
