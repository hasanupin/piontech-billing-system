<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\CommissionRecipient;
use App\Models\User;

class CommissionRecipientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function view(User $user, CommissionRecipient $recipient): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function update(User $user, CommissionRecipient $recipient): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function delete(User $user, CommissionRecipient $recipient): bool
    {
        return $user->isRole(Role::Admin);
    }
}
