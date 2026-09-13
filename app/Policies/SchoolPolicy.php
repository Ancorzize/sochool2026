<?php

namespace App\Policies;

use App\Domain\Tenant\Models\School;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\TenantContext;

class SchoolPolicy
{
    public function view(User $user, School $school): bool
    {
        // Platform Admin can view any school
        if ($user->status->value === 'ACTIVE' && $user->email === 'admin@platform.com') {
            return true;
        }

        return TenantContext::id() === $school->id;
    }

    public function update(User $user, School $school): bool
    {
        if ($user->email === 'admin@platform.com') {
            return true;
        }

        return TenantContext::id() === $school->id;
    }
}
