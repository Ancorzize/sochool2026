<?php

namespace App\Domain\User\Services;

use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;

class RbacAuthorizationService
{
    /**
     * Determine if a user has a specific permission within a target school context
     */
    public function hasPermission(User $user, int $schoolId, string $permissionKey): bool
    {
        $count = DB::connection('pgsql')->table('school_users as su')
            ->join('school_user_roles as sur', function ($join) {
                $join->on('sur.school_user_id', '=', 'su.id')
                     ->on('sur.school_id', '=', 'su.school_id');
            })
            ->join('roles as r', function ($join) {
                $join->on('r.id', '=', 'sur.role_id')
                     ->on('r.school_id', '=', 'sur.school_id');
            })
            ->join('role_permissions as rp', 'rp.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('su.user_id', $user->id)
            ->where('su.school_id', $schoolId)
            ->where('su.status', 'ACTIVE')
            ->where('p.name', $permissionKey)
            ->count();

        return $count > 0;
    }

    /**
     * Retrieve all effective permissions for a user within a target school context
     */
    public function getEffectivePermissions(User $user, int $schoolId): array
    {
        return DB::connection('pgsql')->table('school_users as su')
            ->join('school_user_roles as sur', function ($join) {
                $join->on('sur.school_user_id', '=', 'su.id')
                     ->on('sur.school_id', '=', 'su.school_id');
            })
            ->join('roles as r', function ($join) {
                $join->on('r.id', '=', 'sur.role_id')
                     ->on('r.school_id', '=', 'sur.school_id');
            })
            ->join('role_permissions as rp', 'rp.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('su.user_id', $user->id)
            ->where('su.school_id', $schoolId)
            ->where('su.status', 'ACTIVE')
            ->pluck('p.name')
            ->unique()
            ->values()
            ->toArray();
    }
}
