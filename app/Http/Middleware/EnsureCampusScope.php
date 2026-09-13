<?php

namespace App\Http\Middleware;

use App\Infrastructure\Tenant\CampusContext;
use App\Infrastructure\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureCampusScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestedCampusId = $request->header('X-Campus-ID') ?? $request->input('campus_id');

        if ($requestedCampusId) {
            $campusId = (int) $requestedCampusId;
            $schoolId = TenantContext::id();

            if (!$schoolId) {
                return response()->json(['message' => 'Unauthenticated or tenant not set.'], 403);
            }

            // 1. Verify campus exists, belongs to active tenant, and is ACTIVE
            $campus = DB::table('campuses')
                ->where('id', $campusId)
                ->where('school_id', $schoolId)
                ->where('status', 'ACTIVE')
                ->first();

            if (!$campus) {
                return response()->json(['message' => 'Sede no válida o no pertenece al colegio activo.'], 403);
            }

            CampusContext::set($campusId);
        }

        try {
            return $next($request);
        } finally {
            CampusContext::clear();
        }
    }

    public function terminate($request, $response): void
    {
        CampusContext::clear();
    }
}
