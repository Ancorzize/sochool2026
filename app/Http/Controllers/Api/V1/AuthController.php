<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\User\Services\AuthService;
use App\Domain\User\Services\RbacAuthorizationService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->authService->login($validated['email'], $validated['password']);

        return response()->json([
            'message' => 'Autenticación exitosa.',
            'user' => [
                'id' => $result['user']->id,
                'email' => $result['user']->email,
                'name' => $result['user']->name,
            ],
            'schools' => $result['schools'],
            'pre_tenant_token' => $result['pre_tenant_token'],
        ]);
    }

    public function selectTenant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_id' => 'required|integer',
        ]);

        $result = $this->authService->selectTenant($request->user(), (int) $validated['school_id']);

        return response()->json([
            'message' => 'Colegio seleccionado correctamente.',
            'school_id' => $result['school_id'],
            'school' => $result['school'],
            'tenant_token' => $result['tenant_token'],
        ]);
    }

    public function switchTenant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_school_id' => 'required|integer',
        ]);

        $result = $this->authService->switchTenant($request->user(), (int) $validated['target_school_id']);

        return response()->json([
            'message' => 'Cambio de colegio exitoso.',
            'school_id' => $result['school_id'],
            'school' => $result['school'],
            'tenant_token' => $result['tenant_token'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function me(Request $request, RbacAuthorizationService $rbacService): JsonResponse
    {
        $user = $request->user();
        $schoolId = TenantContext::id();

        $permissions = $schoolId ? $rbacService->getEffectivePermissions($user, $schoolId) : [];

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'status' => $user->status,
            ],
            'active_school_id' => $schoolId,
            'effective_permissions' => $permissions,
        ]);
    }
}
