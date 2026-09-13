<?php

namespace App\Http\Middleware;

use App\Infrastructure\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        $schoolId = null;

        if ($request->hasHeader('X-School-Id')) {
            $schoolId = (int) $request->header('X-School-Id');
        } elseif ($request->user() && isset($request->user()->current_school_id)) {
            $schoolId = (int) $request->user()->current_school_id;
        }

        if ($schoolId && $schoolId > 0) {
            TenantContext::set($schoolId);
        }

        return $next($request);
    }
}
