<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Log aktivitas hanya untuk CEO — super_admin lolos lewat Gate::before di
 * AppServiceProvider, jadi semua role lain ditolak di sini.
 *
 * Tanpa create/update/delete: log append-only, ditulis hanya oleh AuditService.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
