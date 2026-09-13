<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\User\Services\RbacAuthorizationService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantProfileController extends Controller
{
    public function mySchools(Request $request): JsonResponse
    {
        $user = $request->user();

        // Query active memberships using the hardened PL/pgSQL bootstrap function
        $schools = DB::connection('pgsql')->select("SELECT * FROM get_user_active_memberships(?)", [$user->id]);

        return response()->json([
            'data' => $schools,
        ]);
    }

    public function myPermissions(Request $request, RbacAuthorizationService $rbacService): JsonResponse
    {
        $user = $request->user();
        $schoolId = TenantContext::id();

        if (!$schoolId) {
            return response()->json(['message' => 'No hay contexto de colegio activo.'], 403);
        }

        $permissions = $rbacService->getEffectivePermissions($user, $schoolId);

        return response()->json([
            'school_id' => $schoolId,
            'data' => $permissions,
        ]);
    }
}
