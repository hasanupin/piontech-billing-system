<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Cluster;
use App\Models\User;

class ClusterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function view(User $user, Cluster $cluster): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function update(User $user, Cluster $cluster): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function delete(User $user, Cluster $cluster): bool
    {
        return $user->isRole(Role::Admin);
    }
}
