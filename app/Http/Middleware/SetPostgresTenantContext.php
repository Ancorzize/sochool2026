<?php

namespace App\Http\Middleware;

use App\Infrastructure\Tenant\RlsManager;
use App\Infrastructure\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPostgresTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (TenantContext::hasTenant()) {
            RlsManager::setTenantContext(TenantContext::id(), false);
        } else {
            RlsManager::purgeTenantContext();
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        RlsManager::purgeTenantContext();
    }
}
