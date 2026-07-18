<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(Role::Admin, Role::FieldOfficer);
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->isRole(Role::Admin, Role::FieldOfficer);
    }

    public function create(User $user): bool
    {
        // Field officer boleh input transaksi (tunai saja — dibatasi form di TASK-08).
        return $user->isRole(Role::Admin, Role::FieldOfficer);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->isRole(Role::Admin);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->isRole(Role::Admin);
    }
}
