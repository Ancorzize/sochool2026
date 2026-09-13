<?php

namespace App\Http\Middleware;

use App\Domain\User\Services\RbacAuthorizationService;
use App\Infrastructure\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantPermission
{
    protected RbacAuthorizationService $rbacService;

    public function __construct(RbacAuthorizationService $rbacService)
    {
        $this->rbacService = $rbacService;
    }

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $schoolId = TenantContext::id();

        if (!$user || !$schoolId) {
            return response()->json(['message' => 'Contexto de colegio no disponible.'], 403);
        }

        if (!$this->rbacService->hasPermission($user, $schoolId, $permission)) {
            return response()->json(['message' => 'No posee los permisos requeridos para esta acción en el colegio activo.'], 403);
        }

        return $next($request);
    }
}
