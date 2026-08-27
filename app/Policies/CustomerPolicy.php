<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(Role::Admin, Role::FieldOfficer);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->isRole(Role::Admin, Role::FieldOfficer);
    }

    public function create(User $user): bool
    {
        // Pendaftaran pelanggan kembali jadi wewenang admin (PRD §6).
        return $user->isRole(Role::Admin);
    }

    public function update(User $user, Customer $customer): bool
    {
        // Petugas read-only atas data pelanggan (PRD §6).
        return $user->isRole(Role::Admin);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->isRole(Role::Admin);
    }
}
