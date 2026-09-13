<?php

namespace App\Infrastructure\Tenant;

use Illuminate\Support\Facades\DB;

class RlsManager
{
    /**
     * Set PostgreSQL tenant context variable safely using parameterized set_config()
     */
    public static function setTenantContext(int $schoolId, bool $isLocalTransaction = false, ?string $connection = null): void
    {
        TenantContext::set($schoolId);

        $connections = $connection ? [$connection] : ['pgsql', 'pgsql_system'];

        foreach ($connections as $conn) {
            try {
                DB::connection($conn)->statement('SELECT set_config(?, ?, ?)', [
                    'app.current_school_id',
                    (string) $schoolId,
                    $isLocalTransaction
                ]);

                $bypassValue = ($conn === 'pgsql_system') ? 'on' : 'off';
                DB::connection($conn)->statement('SELECT set_config(?, ?, ?)', [
                    'app.bypass_rls',
                    $bypassValue,
                    $isLocalTransaction
                ]);
            } catch (\Throwable $e) {
                // Ignore if connection not configured
            }
        }
    }

    /**
     * Purge PostgreSQL tenant context variable safely and clear in-memory context
     */
    public static function purgeTenantContext(?string $connection = null): void
    {
        TenantContext::clear();

        $connections = $connection ? [$connection] : ['pgsql', 'pgsql_system'];

        foreach ($connections as $conn) {
            try {
                DB::connection($conn)->statement('SELECT set_config(?, ?, false)', [
                    'app.current_school_id',
                    ''
                ]);
                DB::connection($conn)->statement('SELECT set_config(?, ?, false)', [
                    'app.bypass_rls',
                    'off'
                ]);
            } catch (\Throwable $e) {
                // Ignore if connection not configured
            }
        }
    }
}
