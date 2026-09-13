<?php

namespace App\Domain\User\Services;

use App\Domain\User\Enums\UserStatusEnum;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\RlsManager;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate global user credentials and issue Pre-Tenant Access Token
     */
    public function login(string $email, string $password): array
    {
        $user = User::on('pgsql')->where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        $userStatus = $user->status instanceof UserStatusEnum ? $user->status->value : $user->status;
        if ($userStatus !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        // Fetch active school memberships using hardened PL/pgSQL bootstrap function
        $schools = DB::connection('pgsql')->select("SELECT * FROM get_user_active_memberships(?)", [$user->id]);

        $user->last_login_at = now();
        $user->save();

        // Issue Pre-Tenant Token restricted to bootstrap routes
        $preTenantToken = $user->createToken('pre_tenant_token', ['scope:pre-tenant'])->plainTextToken;

        return [
            'user' => $user,
            'schools' => $schools,
            'pre_tenant_token' => $preTenantToken,
        ];
    }

    /**
     * Validate tenant membership and issue Tenant Access Token
     */
    public function selectTenant(User $user, int $schoolId): array
    {
        $userStatus = $user->status instanceof UserStatusEnum ? $user->status->value : $user->status;
        if ($userStatus !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'school_id' => ['Membresía no autorizada o inactiva.'],
            ]);
        }

        $memberships = DB::connection('pgsql')->select("SELECT * FROM get_user_active_memberships(?)", [$user->id]);

        $authorized = collect($memberships)->firstWhere('school_id', $schoolId);

        if (!$authorized || $authorized->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'school_id' => ['Membresía no autorizada o inactiva.'],
            ]);
        }

        // Revoke pre-tenant token
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            DB::table('personal_access_tokens')->where('id', $currentToken->id)->delete();
        }

        // Set RLS tenant context
        RlsManager::setTenantContext($schoolId, false, 'pgsql');

        // Issue Tenant Access Token
        $tenantToken = $user->createToken("tenant_token_{$schoolId}", ["tenant:{$schoolId}"])->plainTextToken;

        return [
            'user' => $user,
            'school_id' => $schoolId,
            'school' => $authorized,
            'tenant_token' => $tenantToken,
        ];
    }

    /**
     * Switch active tenant context securely
     */
    public function switchTenant(User $user, int $targetSchoolId): array
    {
        $userStatus = $user->status instanceof UserStatusEnum ? $user->status->value : $user->status;
        if ($userStatus !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'school_id' => ['Membresía no autorizada o inactiva.'],
            ]);
        }

        $memberships = DB::connection('pgsql')->select("SELECT * FROM get_user_active_memberships(?)", [$user->id]);

        $authorized = collect($memberships)->firstWhere('school_id', $targetSchoolId);

        if (!$authorized || $authorized->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'school_id' => ['Membresía no autorizada o inactiva en el colegio seleccionado.'],
            ]);
        }

        // Revoke current token
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            DB::table('personal_access_tokens')->where('id', $currentToken->id)->delete();
        }

        // Set new tenant context
        RlsManager::setTenantContext($targetSchoolId, false, 'pgsql');

        // Issue new Tenant Access Token for target school
        $tenantToken = $user->createToken("tenant_token_{$targetSchoolId}", ["tenant:{$targetSchoolId}"])->plainTextToken;

        return [
            'user' => $user,
            'school_id' => $targetSchoolId,
            'school' => $authorized,
            'tenant_token' => $tenantToken,
        ];
    }

    /**
     * Logout and revoke current token
     */
    public function logout(User $user): void
    {
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            DB::table('personal_access_tokens')->where('id', $currentToken->id)->delete();
        }

        RlsManager::purgeTenantContext('pgsql');
    }
}
