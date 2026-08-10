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
        // Petugas boleh mendaftarkan pelanggan baru; clusternya divalidasi
        // ScopeService::authorizeCustomerCluster di halaman Create.
        return $user->isRole(Role::Admin, Role::FieldOfficer);
    }

    public function update(User $user, Customer $customer): bool
    {
        if ($user->isRole(Role::Admin)) {
            return true;
        }

        // Petugas hanya boleh mengubah pelanggan di cluster yang dia pegang.
        return $user->isRole(Role::FieldOfficer)
            && $customer->cluster?->officer_id === $user->id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->isRole(Role::Admin);
    }
}
