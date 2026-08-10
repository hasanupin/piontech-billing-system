<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Cluster;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pusat semua data scoping berbasis role — pola diadopsi dari sippw
 * (lihat CLAUDE.md bagian "Roles & Scoping"). Query di resource/service
 * tidak boleh berisi logika scoping sendiri; semua lewat service ini.
 *
 * Saat modul domain billing dibuat (mis. invoice, customer), tambahkan
 * method applyToQuery() dengan match per-role di sini.
 */
class ScopeService
{
    /**
     * Apply user-based scope to a User query based on the actor's role.
     */
    public function scopeUsersForUser(Builder $query, User $actor): Builder
    {
        return match ($actor->role) {
            Role::SuperAdmin => $query,

            // Non-super-admin hanya melihat user yang dia buat sendiri.
            Role::Admin => $query->where('users.created_by', $actor->id),

            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Apply cluster-based scope to a Customer query based on the actor's role.
     * Field officer hanya melihat pelanggan di cluster yang dia pegang.
     */
    public function scopeCustomersForUser(Builder $query, User $actor): Builder
    {
        return match ($actor->role) {
            Role::SuperAdmin, Role::Admin => $query,

            Role::FieldOfficer => $query->whereHas(
                'cluster',
                fn (Builder $q) => $q->where('officer_id', $actor->id),
            ),

            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Apply scope to a Cluster query based on the actor's role.
     * Field officer hanya boleh memakai cluster yang dia pegang.
     */
    public function scopeClustersForUser(Builder $query, User $actor): Builder
    {
        return match ($actor->role) {
            Role::SuperAdmin, Role::Admin => $query,

            Role::FieldOfficer => $query->where('officer_id', $actor->id),

            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Otorisasi tulis: pastikan cluster tujuan pelanggan berada dalam scope aktor.
     */
    public function authorizeCustomerCluster(User $actor, ?string $clusterId): void
    {
        abort_unless(
            filled($clusterId) && $this->scopeClustersForUser(Cluster::query(), $actor)
                ->whereKey($clusterId)
                ->exists(),
            403,
        );
    }

    /**
     * Apply scope to a Transaction query based on the actor's role.
     * Field officer hanya melihat transaksi yang dia catat sebagai petugas.
     */
    public function scopeTransactionsForUser(Builder $query, User $actor): Builder
    {
        return match ($actor->role) {
            Role::SuperAdmin, Role::Admin => $query,

            Role::FieldOfficer => $query->where('officer_id', $actor->id),

            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Apply scope to an OfficerDeposit query based on the actor's role.
     * Field officer hanya melihat setoran miliknya sendiri.
     */
    public function scopeDepositsForUser(Builder $query, User $actor): Builder
    {
        return match ($actor->role) {
            Role::SuperAdmin, Role::Admin => $query,

            Role::FieldOfficer => $query->where('officer_id', $actor->id),

            default => $query->whereRaw('1 = 0'),
        };
    }
}
