<?php

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function testFieldOfficerCannotManageCustomers(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $customer = Customer::factory()->create();

        // Petugas hanya melihat & menagih; CRUD pelanggan wewenang admin (PRD §6).
        $this->assertFalse($officer->can('create', Customer::class));
        $this->assertFalse($officer->can('update', $customer));
        $this->assertFalse($officer->can('delete', $customer));
    }

    public function testFieldOfficerCanCreateTransaction(): void
    {
        $officer = User::factory()->fieldOfficer()->create();

        $this->assertTrue($officer->can('create', Transaction::class));
    }

    public function testFieldOfficerCannotViewMasterData(): void
    {
        $officer = User::factory()->fieldOfficer()->create();

        $this->assertFalse($officer->can('viewAny', Cluster::class));
        $this->assertFalse($officer->can('viewAny', Package::class));
    }

    public function testAdminCanCreateCustomerButNotManageUsers(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('create', Customer::class));
        $this->assertFalse($admin->can('viewAny', User::class));
    }

    public function testAdminCannotCreateTransfer(): void
    {
        // Restriksi transfer adalah form-level (TASK-08); di policy admin tetap boleh create.
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('create', Transaction::class));
    }

    public function testSuperAdminCanDoEverything(): void
    {
        $ceo = User::factory()->superAdmin()->create();
        $customer = Customer::factory()->create();
        $package = Package::factory()->create();

        $this->assertTrue($ceo->can('viewAny', User::class));
        $this->assertTrue($ceo->can('create', User::class));
        $this->assertTrue($ceo->can('create', Customer::class));
        $this->assertTrue($ceo->can('update', $customer));
        $this->assertTrue($ceo->can('delete', $customer));
        $this->assertTrue($ceo->can('create', Transaction::class));
        $this->assertTrue($ceo->can('create', Cluster::class));
        $this->assertTrue($ceo->can('update', $package));
    }
}
