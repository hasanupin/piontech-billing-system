<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Package;
use App\Models\User;

class PackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function view(User $user, Package $package): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function update(User $user, Package $package): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function delete(User $user, Package $package): bool
    {
        return $user->isRole(Role::Admin);
    }
}
