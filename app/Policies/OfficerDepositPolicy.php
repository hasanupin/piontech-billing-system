<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\OfficerDeposit;
use App\Models\User;

class OfficerDepositPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(Role::Admin, Role::FieldOfficer);
    }

    public function view(User $user, OfficerDeposit $deposit): bool
    {
        return $user->isRole(Role::Admin, Role::FieldOfficer);
    }

    public function create(User $user): bool
    {
        // Petugas inisiasi setoran, admin konfirmasi.
        return $user->isRole(Role::Admin, Role::FieldOfficer);
    }

    public function update(User $user, OfficerDeposit $deposit): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function delete(User $user, OfficerDeposit $deposit): bool
    {
        return $user->isRole(Role::Admin);
    }
}
