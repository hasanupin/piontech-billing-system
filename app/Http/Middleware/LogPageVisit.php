<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catat akses menu. Dipasang di authMiddleware panel, jadi hanya route panel
 * yang sudah login yang lewat sini — login/password-reset tidak ikut, dan
 * request Livewire (/livewire/update) bukan route panel sehingga tidak spam.
 */
class LogPageVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Setelah response: 403 dan redirect bukan "akses menu".
        if ($request->isMethod('GET') && $response->isSuccessful()) {
            app(AuditService::class)->recordPageVisit($request);
        }

        return $response;
    }
}
