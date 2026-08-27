<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserModuleTest extends TestCase
{
    use RefreshDatabase;

    public function testSuperAdminCanAccessUsersResource(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk();
    }

    public function testNonSuperAdminCannotAccessUsersResource(): void
    {
        $fieldOfficer = User::factory()->create();

        $this->actingAs($fieldOfficer)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function testInactiveUserCannotAccessPanel(): void
    {
        $inactive = User::factory()->superAdmin()->inactive()->create();

        $this->actingAs($inactive)
            ->get('/admin')
            ->assertForbidden();
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $this->get('/admin/users')->assertRedirect('/admin/login');
    }

    public function testFieldOfficerGetsDefaultCommissionPerCustomer(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Petugas Baru',
                'username' => 'petugas.baru',
                'email' => 'petugas.baru@example.com',
                'password' => 'password',
                'role' => Role::FieldOfficer->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            4000.0,
            (float) User::where('username', 'petugas.baru')->value('commission_per_customer'),
        );
    }

    public function testCommissionPerCustomerIsStoredPerOfficer(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Petugas Mahal',
                'username' => 'petugas.mahal',
                'email' => 'petugas.mahal@example.com',
                'password' => 'password',
                'role' => Role::FieldOfficer->value,
                'commission_per_customer' => 7500,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            7500.0,
            (float) User::where('username', 'petugas.mahal')->value('commission_per_customer'),
        );
    }

    public function testCommissionFieldIsHiddenForNonOfficerRoles(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm(['role' => Role::Admin->value])
            ->assertFormFieldHidden('commission_per_customer');
    }
}
