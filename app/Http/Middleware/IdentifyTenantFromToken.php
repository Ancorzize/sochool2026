<?php

namespace App\Http\Middleware;

use App\Domain\User\Enums\UserStatusEnum;
use App\Infrastructure\Tenant\RlsManager;
use App\Infrastructure\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantFromToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $userStatus = $user->status instanceof UserStatusEnum ? $user->status->value : $user->status;
        if ($userStatus !== 'ACTIVE') {
            return response()->json(['message' => 'Usuario inactivo.'], 403);
        }

        $token = $user->currentAccessToken();
        if (!$token) {
            return response()->json(['message' => 'Token no válido.'], 401);
        }

        // Find tenant ability matching 'tenant:{school_id}'
        $tenantAbility = collect($token->abilities)->first(fn($a) => str_starts_with($a, 'tenant:'));

        if (!$tenantAbility) {
            return response()->json(['message' => 'Token no configurado para un colegio. Se requiere seleccionar colegio.'], 403);
        }

        $schoolId = (int) str_replace('tenant:', '', $tenantAbility);

        // Verify active membership using the hardened PL/pgSQL bootstrap function
        $memberships = DB::connection('pgsql')->select("SELECT * FROM get_user_active_memberships(?)", [$user->id]);
        $authorized = collect($memberships)->firstWhere('school_id', $schoolId);

        if (!$authorized || $authorized->status !== 'ACTIVE') {
            return response()->json(['message' => 'Membresía inactiva o no autorizada para este colegio.'], 403);
        }

        // Set memory and PostgreSQL RLS tenant context
        TenantContext::setSchoolId($schoolId);
        RlsManager::setTenantContext($schoolId, false, 'pgsql');

        try {
            return $next($request);
        } finally {
            RlsManager::purgeTenantContext('pgsql');
        }
    }

    public function terminate(Request $request, Response $response): void
    {
        RlsManager::purgeTenantContext('pgsql');
    }
}
