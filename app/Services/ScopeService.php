<?php

namespace App\Services;

use App\Enums\Role;
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
}
