<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
