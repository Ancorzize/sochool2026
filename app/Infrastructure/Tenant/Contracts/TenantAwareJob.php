<?php

namespace App\Infrastructure\Tenant\Contracts;

use App\Infrastructure\Tenant\RlsManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

abstract class TenantAwareJob implements ShouldQueue
{
    use Queueable;

    public int $schoolId;

    public function __construct(int $schoolId)
    {
        $this->schoolId = $schoolId;
    }

    /**
     * Business logic for the tenant job
     */
    abstract protected function executeJob(): void;

    /**
     * Wrap job execution inside tenant context and ensure context is purged on completion
     */
    public function handle(): void
    {
        RlsManager::setTenantContext($this->schoolId);

        try {
            $this->executeJob();
        } finally {
            RlsManager::purgeTenantContext();
        }
    }
}
